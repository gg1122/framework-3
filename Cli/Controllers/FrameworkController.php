<?php

namespace ManaPHP\Cli\Controllers;

use ManaPHP\Cli\Controller;
use ManaPHP\Utility\Text;

class FrameworkController extends Controller
{
    /**
     * @var string
     */
    protected $_tmpLiteFile = '@root/manaphp_lite.tmp';

    /**
     * @CliCommand build manaphp framework lite php file
     * @CliParam   --config,-c  config file name default:@root/manaphp_lite.json
     * @CliParam   --output,-o  output file name default:@root/manaphp_lite.php
     *
     */
    public function liteCommand()
    {
        if (!$this->filesystem->fileExists('@root/manaphp_lite.json')) {
            $this->filesystem->fileCopy('@manaphp/manaphp_lite.json', '@root/manaphp_lite.json');
        }

        $jsonFile = $this->arguments->get('input:i', '@root/manaphp_lite.json');
        $config = json_decode($this->filesystem->fileGet($jsonFile), true);

        if (isset($config['output'])) {
            $outputFile = $config['output'];
        } else {
            $outputFile = $this->arguments->get('output:o', '@root/manaphp_lite.php');
        }

        $contents = '';

        foreach ($config['classes'] as $c) {
            if (strpos($c, 'ManaPHP\\') !== 0) {
                continue;
            }

            $file = '@manaphp/' . strtr(substr($c, strpos($c, '\\')), '\\', '/') . '.php';

            if (!$this->filesystem->fileExists($file)) {
                return $this->console->error('`:file` is not missing for `:class` class', ['file' => $file, 'class' => $c]);
            }

            $content = $this->filesystem->fileGet($file);

            if (preg_match('#\s+implements\s+.*#', $content, $matches) === 1) {
                $implements = $matches[0];
                $implements = preg_replace('#[a-zA-Z]+Interface,?#', '', $implements);
                if (str_replace([',', ' ', "\r", "\n"], '', $implements) === 'implements') {
                    $implements = '';
                }
                $content = str_replace($matches[0], $implements, $content);
            }

            $contents .= '/**' . $c . '*/' . preg_replace('#^\s*<\?php\s*#', '', $this->_strip_whitespace($content), 1) . PHP_EOL;
        }

        $contents = '<?php' . PHP_EOL . $contents;

        $this->filesystem->filePut($outputFile, $contents);

        $this->console->writeLn('lite file generated in `:output` successfully ', ['output' => $outputFile]);

        return 0;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function _strip_whitespace($str)
    {
        $this->filesystem->filePut($this->_tmpLiteFile, $str);
        $str = php_strip_whitespace($this->alias->resolve($this->_tmpLiteFile));
//        $str = preg_replace('#\s*/\*\*.*?\*/#ms', '', $str);//remove comments
//        $str = preg_replace('#([\r\n]+)\s*\\1#', '\\1', $str);//remove blank lines
//        $str = preg_replace('#([\r\n]+)\s+{#', '{', $str);//repositionClose;

        return $str;
    }

    public function __destruct()
    {
        $this->filesystem->fileDelete($this->_tmpLiteFile);
    }

    protected function _getSourceFiles($dir)
    {
        $files = [];

        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if (Text::startsWith($file, '.')) {
                continue;
            }

            $file = strtr($dir, '\\', '/') . '/' . $file;
            if (is_dir($file)) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $files = array_merge($files, $this->_getSourceFiles($file));
            } else {
                if (fnmatch('*.php', $file)) {
                    $files[] = $file;
                }
            }
        }

        closedir($dh);

        return $files;
    }

    protected function _minify($content)
    {
        $content = preg_replace('#\s*/\*\*.*?\*/#ms', '', $content);//remove comments
        $content = preg_replace('#([\r\n]+)\s*\\1#', '\\1', $content);//remove blank lines
        $content = preg_replace('#([\r\n]+)\s+{#', '{', $content);//repositionClose;

        return $content;
    }

    /**
     * @CliCommand minify framework source code
     * @return int
     */
    public function minifyCommand()
    {
        $ManaPHPSrcDir = $this->alias->get('@manaphp');
        $ManaPHPDstDir = $ManaPHPSrcDir . '_' . date('ymd');
        $totalClassLines = 0;
        $totalInterfaceLines = 0;
        $totalLines = 0;
        $fileLines = [];
        $sourceFiles = $this->_getSourceFiles($ManaPHPSrcDir);
        foreach ($sourceFiles as $file) {
            $dstFile = str_replace($ManaPHPSrcDir, $ManaPHPDstDir, $file);

            $content = $this->_minify($this->filesystem->fileGet($file));
            $lineCount = substr_count($content, Text::contains($content, "\r") ? "\r" : "\n");

            if (Text::contains($file, 'Interface.php')) {
                $totalInterfaceLines += $lineCount;
                $totalLines += $lineCount;
            } else {
                $totalClassLines += $lineCount;
                $totalLines += $lineCount;
            }

            $this->console->writeLn($content);
            $this->filesystem->filePut($dstFile, $content);
            $fileLines[$file] = $lineCount;
        }

        asort($fileLines);

        $i = 1;
        $this->console->writeLn('------------------------------------------------------');

        foreach ($fileLines as $file => $line) {
            $this->console->writeLn(sprintf('%3d %3d %.3f', $i++, $line, $line / $totalLines * 100) . ' ' . substr($file, strpos($file, 'ManaPHP')));
        }

        $this->console->writeLn('------------------------------------------------------');
        $this->console->writeLn('total     lines: ' . $totalLines);
        $this->console->writeLn('class     lines: ' . $totalClassLines);
        $this->console->writeLn('interface lines:  ' . $totalInterfaceLines);

        return 0;
    }
}