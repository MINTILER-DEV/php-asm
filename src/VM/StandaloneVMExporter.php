<?php

/**
 * Standalone VM Exporter
 * Generates a single PHP file with embedded bytecode and VM runtime.
 */
class StandaloneVMExporter {
    public function export($inputPhcFile, $outputPhpFile) {
        if (!file_exists($inputPhcFile)) {
            throw new Exception("Bytecode file not found: $inputPhcFile");
        }

        $binary = file_get_contents($inputPhcFile);
        if ($binary === false) {
            throw new Exception("Failed to read bytecode file: $inputPhcFile");
        }

        $outputDir = dirname($outputPhpFile);
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
                throw new Exception("Failed to create output directory: $outputDir");
            }
        }

        $hex = bin2hex($binary);
        $hexLiteral = $this->formatHexLiteral($hex, 120);
        $vmSource = $this->buildEmbeddedVMSource();
        $generatedAt = date('Y-m-d H:i:s T');
        $sourceFile = basename($inputPhcFile);

        $output = <<<PHP
<?php

/**
 * Standalone PHC VM Runner
 * Generated: {$generatedAt}
 * Source: {$sourceFile}
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\\n");
}

\$embeddedHex =
{$hexLiteral}
;

{$vmSource}

function decodeEmbeddedPhc(\$hex) {
    if (\$hex === '') {
        return '';
    }

    \$binary = hex2bin(\$hex);
    if (\$binary !== false) {
        return \$binary;
    }

    \$bytes = [];
    \$length = strlen(\$hex);
    for (\$i = 0; \$i < \$length; \$i += 2) {
        \$bytes[] = hexdec(\$hex[\$i] . \$hex[\$i + 1]);
    }

    return pack('C*', ...\$bytes);
}

\$verbose = in_array('--verbose', \$argv, true) || in_array('-v', \$argv, true);
\$binaryBytecode = decodeEmbeddedPhc(\$embeddedHex);
\$tempFile = tempnam(sys_get_temp_dir(), 'phc_embedded_');

if (\$tempFile === false) {
    throw new Exception("Unable to create temporary bytecode file.");
}

file_put_contents(\$tempFile, \$binaryBytecode);

try {
    \$vm = new PHCVM();
    \$vm->setVerbose(\$verbose);
    \$vm->load(\$tempFile);
    \$vm->run();
} finally {
    @unlink(\$tempFile);
}
PHP;

        if (file_put_contents($outputPhpFile, $output) === false) {
            throw new Exception("Failed to write output file: $outputPhpFile");
        }
    }

    private function formatHexLiteral($hex, $chunkSize) {
        if ($hex === '') {
            return "    ''";
        }

        $chunks = str_split($hex, $chunkSize);
        $lines = [];
        $lastIndex = count($chunks) - 1;

        foreach ($chunks as $index => $chunk) {
            $suffix = ($index === $lastIndex) ? '' : ' .';
            $lines[] = "    '$chunk'$suffix";
        }

        return implode("\n", $lines);
    }

    private function buildEmbeddedVMSource() {
        $files = [
            __DIR__ . '/../Opcodes.php',
            __DIR__ . '/../MemoryLayout.php',
            __DIR__ . '/../BuiltinFunctions.php',
            __DIR__ . '/Stack.php',
            __DIR__ . '/Memory.php',
            __DIR__ . '/SyscallHandler.php',
            __DIR__ . '/BytecodeLoader.php',
            __DIR__ . '/PHCVM.php',
        ];

        $parts = [];
        foreach ($files as $file) {
            $parts[] = $this->stripFileBoilerplate($file);
        }

        return implode("\n\n", $parts);
    }

    private function stripFileBoilerplate($file) {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new Exception("Failed to read runtime source file: $file");
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $content = preg_replace('/^\s*<\?php\s*/', '', $content, 1);
        $content = preg_replace('/^\s*require_once\s+[^;]+;\s*$/m', '', $content);

        return trim($content);
    }
}
