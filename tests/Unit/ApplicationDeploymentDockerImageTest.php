<?php

use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class TestableDockerImageDeploymentJob extends ApplicationDeploymentJob
{
    public array $remoteCommands = [];

    public Collection $remoteOutputs;

    public string $dockerfileOutput;

    public function execute_remote_command(...$commands): void
    {
        foreach ($commands as $command) {
            $this->remoteCommands[] = $command;
            if (($command['save'] ?? null) === 'dockerfile_from_repo') {
                $this->remoteOutputs->put('dockerfile_from_repo', $this->dockerfileOutput);
            }
        }
    }
}

function generateDockerImageCompose(array $attributes = [], string $dockerfileOutput = ''): array
{
    $team = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $team->id]);
    $environment = Environment::where('project_id', $project->id)->firstOrFail();
    $server = Server::factory()->create(['team_id' => $team->id]);
    $destination = $server->standaloneDockers()->firstOrFail();
    $application = Application::factory()->create([
        'environment_id' => $environment->id,
        'destination_id' => $destination->id,
        'destination_type' => StandaloneDocker::class,
        'build_pack' => 'dockerimage',
        'dockerfile' => "FROM alpine:3.21\nHEALTHCHECK --interval=99s CMD true",
        'docker_registry_image_name' => 'registry.example.com/team/app',
        'docker_registry_image_tag' => 'sha256-'.str_repeat('a', 64),
        'health_check_enabled' => true,
        'health_check_type' => 'cmd',
        'health_check_command' => 'curl -f http://localhost:3000/ready',
        'health_check_interval' => 23,
        'health_check_timeout' => 7,
        'health_check_retries' => 4,
        'health_check_start_period' => 11,
        'custom_healthcheck_found' => false,
        ...$attributes,
    ]);
    $queue = ApplicationDeploymentQueue::create([
        'application_id' => $application->id,
        'deployment_uuid' => 'deployment-uuid',
        'status' => 'queued',
    ]);
    $job = (new ReflectionClass(TestableDockerImageDeploymentJob::class))->newInstanceWithoutConstructor();
    $job->remoteOutputs = new Collection;
    $job->dockerfileOutput = $dockerfileOutput;
    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);

    foreach ([
        'application' => $application,
        'application_deployment_queue' => $queue,
        'destination' => $destination,
        'server' => $server,
        'mainServer' => $server,
        'pull_request_id' => 0,
        'commit' => 'HEAD',
        'container_name' => 'docker-image-app',
        'dockerImage' => $application->docker_registry_image_name,
        'dockerImageTag' => $application->docker_registry_image_tag,
        'deployment_uuid' => 'deployment-uuid',
        'workdir' => '/artifacts/docker-image-app',
        'configuration_dir' => '/data/coolify/applications/test',
        'saved_outputs' => $job->remoteOutputs,
    ] as $property => $value) {
        $reflection->getProperty($property)->setValue($job, $value);
    }

    $reflection->getMethod('generate_image_names')->invoke($job);
    $reflection->getMethod('generate_compose_file')->invoke($job);
    $compose = Yaml::parse($reflection->getProperty('docker_compose')->getValue($job));

    return [$compose['services']['docker-image-app'], $application, $job->remoteCommands];
}

it('renders the configured image digest and healthcheck without reading a stale Dockerfile', function () {
    [$service, , $commands] = generateDockerImageCompose();

    expect($service['image'])->toBe('registry.example.com/team/app@sha256:'.str_repeat('a', 64));
    expect($service['healthcheck'])->toBe([
        'test' => ['CMD-SHELL', 'curl -f http://localhost:3000/ready'],
        'interval' => '23s',
        'timeout' => '7s',
        'retries' => 4,
        'start_period' => '11s',
    ]);
    expect(array_column($commands, 'save'))->not->toContain('dockerfile_from_repo');
});

it('preserves disabled image healthchecks instead of importing stale Dockerfile settings', function () {
    [$service, $application, $commands] = generateDockerImageCompose(
        ['health_check_enabled' => false],
        'FROM alpine:3.21'."\n".'HEALTHCHECK --interval=99s --timeout=88s --retries=77 --start-period=66s CMD true',
    );

    expect($service)->not->toHaveKey('healthcheck');
    expect(array_column($commands, 'save'))->not->toContain('dockerfile_from_repo');
    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'health_check_enabled' => false,
        'custom_healthcheck_found' => false,
        'health_check_interval' => 23,
        'health_check_timeout' => 7,
        'health_check_retries' => 4,
        'health_check_start_period' => 11,
    ]);
});

it('preserves image healthcheck metadata when the stale Dockerfile is unavailable', function () {
    [$service, $application] = generateDockerImageCompose(['custom_healthcheck_found' => true]);

    expect($service)->not->toHaveKey('healthcheck');
    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'custom_healthcheck_found' => true,
        'health_check_interval' => 23,
        'health_check_timeout' => 7,
        'health_check_retries' => 4,
        'health_check_start_period' => 11,
    ]);
});

it('still imports Dockerfile healthchecks for builds', function (string $buildPack, ?string $dockerfile) {
    [$service, $application, $commands] = generateDockerImageCompose(
        [
            'build_pack' => $buildPack,
            'dockerfile' => $dockerfile,
            'health_check_enabled' => false,
        ],
        'FROM alpine:3.21'."\n".'HEALTHCHECK --interval=99s --timeout=88s --retries=77 --start-period=66s CMD true',
    );

    expect($service)->not->toHaveKey('healthcheck');
    expect(array_column($commands, 'save'))->toContain('dockerfile_from_repo');
    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'custom_healthcheck_found' => true,
        'health_check_interval' => 99,
        'health_check_timeout' => 88,
        'health_check_retries' => 77,
        'health_check_start_period' => 66,
    ]);
})->with([
    'repository Dockerfile' => ['dockerfile', null],
    'legacy inline Dockerfile' => ['nixpacks', 'FROM alpine:3.21'],
]);
