<?php

test('Hostname', function () {
    $input = '--hostname=test';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'hostname' => 'test',
    ]);
});
test('HostnameWithoutEqualSign', function () {
    $input = '--hostname test';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'hostname' => 'test',
    ]);
});
test('HostnameWithoutEqualSignAndHyphens', function () {
    $input = '--hostname my-super-host';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'hostname' => 'my-super-host',
    ]);
});

test('HostnameWithHyphens', function () {
    $input = '--hostname=my-super-host';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'hostname' => 'my-super-host',
    ]);
});
test('ConvertCapAdd', function () {
    $input = '--cap-add=NET_ADMIN --cap-add=NET_RAW --cap-add SYS_ADMIN';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'cap_add' => ['NET_ADMIN', 'NET_RAW', 'SYS_ADMIN'],
    ]);
});

test('ConvertIp', function () {
    $input = '--cap-add=NET_ADMIN --cap-add=NET_RAW --cap-add SYS_ADMIN --ip 127.0.0.1 --ip 127.0.0.2';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'cap_add' => ['NET_ADMIN', 'NET_RAW', 'SYS_ADMIN'],
        'ip' => ['127.0.0.1', '127.0.0.2'],
    ]);
});

test('ConvertDns', function () {
    $input = '--dns 10.0.0.10 --dns=1.1.1.1';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'dns' => ['10.0.0.10', '1.1.1.1'],
    ]);
});

test('ConvertDnsWithOtherOptions', function () {
    $input = '--cap-add=NET_ADMIN --dns 10.0.0.10 --init';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'cap_add' => ['NET_ADMIN'],
        'dns' => ['10.0.0.10'],
        'init' => true,
    ]);
});

test('ConvertPrivilegedAndInit', function () {
    $input = '---privileged --init';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'privileged' => true,
        'init' => true,
    ]);
});

test('ConvertUlimit', function () {
    $input = '--ulimit nofile=262144:262144';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'ulimits' => [
            'nofile' => [
                'soft' => '262144',
                'hard' => '262144',
            ],
        ],
    ]);
});
test('ConvertGpusWithGpuId', function () {
    $input = '--gpus "device=GPU-0000000000000000"';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'deploy' => [
            'resources' => [
                'reservations' => [
                    'devices' => [
                        [
                            'driver' => 'nvidia',
                            'capabilities' => ['gpu'],
                            'device_ids' => ['GPU-0000000000000000'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

test('ConvertGpus', function () {
    $input = '--gpus all';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'deploy' => [
            'resources' => [
                'reservations' => [
                    'devices' => [
                        [
                            'driver' => 'nvidia',
                            'capabilities' => ['gpu'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

test('ConvertGpusWithQuotes', function () {
    $input = '--gpus "device=0,1"';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'deploy' => [
            'resources' => [
                'reservations' => [
                    'devices' => [
                        [
                            'driver' => 'nvidia',
                            'capabilities' => ['gpu'],
                            'device_ids' => ['0', '1'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

test('ConvertEntrypointSimple', function () {
    $input = '--entrypoint /bin/sh';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => '/bin/sh',
    ]);
});

test('ConvertEntrypointWithEquals', function () {
    $input = '--entrypoint=/bin/bash';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => '/bin/bash',
    ]);
});

test('ConvertEntrypointWithArguments', function () {
    $input = '--entrypoint "sh -c npm install"';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => 'sh -c npm install',
    ]);
});

test('ConvertEntrypointWithSingleQuotes', function () {
    $input = "--entrypoint 'memcached -m 256'";
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => 'memcached -m 256',
    ]);
});

test('ConvertEntrypointWithOtherOptions', function () {
    $input = '--entrypoint /bin/bash --cap-add SYS_ADMIN --privileged';
    $output = convertDockerRunToCompose($input);
    expect($output)->toHaveKeys(['entrypoint', 'cap_add', 'privileged'])
        ->and($output['entrypoint'])->toBe('/bin/bash')
        ->and($output['cap_add'])->toBe(['SYS_ADMIN'])
        ->and($output['privileged'])->toBe(true);
});

test('ConvertEntrypointComplex', function () {
    $input = '--entrypoint "sh -c \'npm install && npm start\'"';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => "sh -c 'npm install && npm start'",
    ]);
});

test('ConvertEntrypointWithEscapedDoubleQuotes', function () {
    $input = '--entrypoint "python -c \"print(\'hi\')\""';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => "python -c \"print('hi')\"",
    ]);
});

test('ConvertEntrypointWithEscapedSingleQuotesInDoubleQuotes', function () {
    $input = '--entrypoint "sh -c \"echo \'hello\'\""';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => "sh -c \"echo 'hello'\"",
    ]);
});

test('ConvertEntrypointSingleQuotedWithDoubleQuotesInside', function () {
    $input = '--entrypoint \'python -c "print(\"hi\")"\'';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'entrypoint' => 'python -c "print(\"hi\")"',
    ]);
});

test('ConvertIp6', function () {
    $input = '--ip6 2001:db8::1';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'ip6' => ['2001:db8::1'],
    ]);
});

test('ConvertIpAndIp6Together', function () {
    $input = '--ip 172.20.0.5 --ip6 2001:db8::1';
    $output = convertDockerRunToCompose($input);
    expect($output)->toBe([
        'ip' => ['172.20.0.5'],
        'ip6' => ['2001:db8::1'],
    ]);
});

test('preserves complete hyphenated security option values', function (string $input) {
    expect(convertDockerRunToCompose($input))->toBe([
        'security_opt' => ['no-new-privileges:true', 'seccomp=/etc/docker/custom-profile.json'],
    ]);
})->with([
    'space separated' => '--security-opt no-new-privileges:true --security-opt seccomp=/etc/docker/custom-profile.json',
    'equals separated' => '--security-opt=no-new-privileges:true --security-opt=seccomp=/etc/docker/custom-profile.json',
    'double quoted' => '--security-opt "no-new-privileges:true" --security-opt="seccomp=/etc/docker/custom-profile.json"',
    'single quoted' => "--security-opt='no-new-privileges:true' --security-opt 'seccomp=/etc/docker/custom-profile.json'",
]);

test('preserves quoted hostnames and hyphenated device paths', function () {
    $input = '--hostname="my-super-host" --device /dev/disk/by-id/example-device';

    expect(convertDockerRunToCompose($input))->toBe([
        'hostname' => 'my-super-host',
        'devices' => ['/dev/disk/by-id/example-device'],
    ]);
});

test('converts read-only options to Compose booleans', function (string $input, bool $expected) {
    expect(convertDockerRunToCompose($input))->toBe(['read_only' => $expected]);
})->with([
    'bare flag' => ['--read-only', true],
    'explicit true' => ['--read-only=true', true],
    'explicit false' => ['--read-only=false', false],
    'double quoted true' => ['--read-only="true"', true],
    'single quoted false' => ["--read-only='false'", false],
    'numeric true' => ['--read-only=1', true],
    'numeric false' => ['--read-only=0', false],
    'last value wins' => ['--read-only=false --read-only', true],
    'trailing whitespace' => ['--read-only   ', true],
]);

test('allows whitespace between bare read-only and the next flag', function (string $input) {
    expect(convertDockerRunToCompose($input))->toBe(['read_only' => true, 'init' => true]);
})->with([
    'multiple spaces' => '--read-only  --init',
    'multiple newlines' => "--read-only\n\n--init",
]);

test('preserves repeated tmpfs mounts and their full options', function (string $input) {
    expect(convertDockerRunToCompose($input))->toBe([
        'tmpfs' => ['/tmp:rw,noexec,nosuid,size=64m,mode=1777', '/run/app-cache:rw,nosuid,size=16m'],
    ]);
})->with([
    'unquoted' => '--tmpfs /tmp:rw,noexec,nosuid,size=64m,mode=1777 --tmpfs /run/app-cache:rw,nosuid,size=16m',
    'double quoted' => '--tmpfs="/tmp:rw,noexec,nosuid,size=64m,mode=1777" --tmpfs="/run/app-cache:rw,nosuid,size=16m"',
    'single quoted' => "--tmpfs '/tmp:rw,noexec,nosuid,size=64m,mode=1777' --tmpfs '/run/app-cache:rw,nosuid,size=16m'",
]);

test('combines read-only and tmpfs with existing runtime options', function () {
    $input = '--read-only --cap-drop ALL --security-opt no-new-privileges:true --tmpfs /tmp:rw,noexec,size=64m --dns 1.1.1.1 --dns 8.8.8.8 --init';

    expect(convertDockerRunToCompose($input))->toBe([
        'read_only' => true,
        'cap_drop' => ['ALL'],
        'security_opt' => ['no-new-privileges:true'],
        'tmpfs' => ['/tmp:rw,noexec,size=64m'],
        'dns' => ['1.1.1.1', '8.8.8.8'],
        'init' => true,
    ]);
});

test('rejects missing or malformed runtime option values', function (string $input) {
    expect(fn () => convertDockerRunToCompose($input))->toThrow(InvalidArgumentException::class);
})->with([
    'invalid boolean' => '--read-only=invalid',
    'empty boolean' => '--read-only=',
    'quoted empty boolean' => '--read-only=""',
    'missing tmpfs' => '--tmpfs',
    'empty tmpfs' => '--tmpfs=',
    'quoted empty tmpfs' => '--tmpfs ""',
    'next flag is not a tmpfs value' => '--tmpfs --read-only',
    'missing security option' => '--security-opt',
    'empty security option' => '--security-opt=',
    'quoted empty security option' => '--security-opt ""',
    'unterminated double quote' => '--security-opt "unterminated',
    'unterminated single quote' => "--security-opt 'unterminated",
    'flag is not an assigned boolean' => '--read-only=--init',
    'boolean quoted suffix' => '--read-only="false"junk',
    'security option quoted suffix' => '--security-opt "no-new-privileges:true"suffix',
    'unmatched quote inside tmpfs value' => '--tmpfs /tmp" --privileged',
]);

test('keeps flag-looking text inside quoted option values', function () {
    expect(convertDockerRunToCompose('--security-opt "no-new-privileges:true --privileged --read-only=false"'))
        ->toBe(['security_opt' => ['no-new-privileges:true --privileged --read-only=false']]);
});

test('does not reparse quoted contents of unknown options', function () {
    expect(convertDockerRunToCompose('--unknown "value --privileged --read-only=false" --init'))
        ->toBe(['init' => true]);
});

test('uses the entrypoint value from its own option', function () {
    expect(convertDockerRunToCompose('--unknown "--entrypoint /bin/sh" --entrypoint /bin/bash'))
        ->toBe(['entrypoint' => '/bin/bash']);
});

test('uses GPU devices from their own option', function () {
    expect(convertDockerRunToCompose('--unknown "--gpus device=1" --gpus all'))
        ->toBe([
            'deploy' => [
                'resources' => [
                    'reservations' => [
                        'devices' => [[
                            'driver' => 'nvidia',
                            'capabilities' => ['gpu'],
                        ]],
                    ],
                ],
            ],
        ]);
});

test('returns no custom settings for empty inputs', function (?string $input) {
    expect(convertDockerRunToCompose($input))->toBe([]);
})->with([null, '', '   ']);
