<?php
/**
 * Tests the .env reader used for the Gemini key.
 * Run: C:\xampp\php\php.exe scripts\tests\chatbox_env.php
 * Each case runs in a child PHP process with its own temporary .env file
 * (LUXURY_CHATBOX_ENV_FILE), so the real .env is never read or changed.
 */
$root = dirname(__DIR__, 2);
$passed = 0;
$failed = [];
function check($name, $condition, $detail = '') {
    global $passed, $failed;
    if ($condition) { $passed++; echo "  ok  $name\n"; }
    else { $failed[] = $name; echo "  FAIL $name" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

/** Returns [key, source] as seen by the plugin with the given .env content and environment. */
function key_with_env($content, $env_value = null) {
    global $root;
    $file = tempnam(sys_get_temp_dir(), 'lcenv');
    if ($content !== null) file_put_contents($file, $content); else unlink($file);
    $code = 'define("LUXURY_CHATBOX_ENV_FILE", ' . var_export($file, true) . '); require ' . var_export($root . '/wordpress/wp-load.php', true) . '; echo json_encode(LC_Settings::api_key());';
    $env = getenv();
    unset($env['GEMINI_API_KEY']);
    if ($env_value !== null) $env['GEMINI_API_KEY'] = $env_value;
    $proc = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
    if (is_file($file)) unlink($file);
    return json_decode($out, true) ?: ['', 'error: ' . $out];
}

$k = 'test-key-' . bin2hex(random_bytes(6));
check('reads GEMINI_API_KEY from .env', key_with_env("GEMINI_API_KEY=$k\n") === [$k, 'file .env']);
check('ignores comments and blank lines', key_with_env("# note\n\n# GEMINI_API_KEY=wrong\nGEMINI_API_KEY=$k\n") === [$k, 'file .env']);
check('accepts double quotes', key_with_env("GEMINI_API_KEY=\"$k\"\n") === [$k, 'file .env']);
check('accepts single quotes', key_with_env("GEMINI_API_KEY='$k'\n") === [$k, 'file .env']);
check('accepts "export" and spaces around =', key_with_env("export GEMINI_API_KEY = $k\n") === [$k, 'file .env']);
check('strips an inline comment', key_with_env("GEMINI_API_KEY=$k # personal key\n") === [$k, 'file .env']);
check('handles Windows line endings', key_with_env("GEMINI_API_KEY=$k\r\n") === [$k, 'file .env']);
check('empty value means not configured', key_with_env("GEMINI_API_KEY=\n") === ['', '']);
check('missing .env means not configured', key_with_env(null) === ['', '']);
check('a real environment variable wins over .env', key_with_env("GEMINI_API_KEY=$k\n", 'from-env') === ['from-env', 'biến môi trường']);
check('other variables in .env are not exposed', key_with_env("OTHER_SECRET=x\nGEMINI_API_KEY=$k\n") === [$k, 'file .env']);

echo "\n{$passed} passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
