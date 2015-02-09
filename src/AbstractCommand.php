<?php

namespace Magext;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class AbstractCommand extends Command
{
    const PS = '/';
    const MSG_TEXT_PAD = 10;

    public static function parseModmanFile(OutputInterface $output, $modmanDir)
    {
        $config = array();
        $config['imports'] = array();
        $config['mapping'] = array();
        $config['shell_commands'] = array();

        $handle = @fopen($modmanDir.'/modman', "r");
        $lineCounter = 0;
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $buffer = preg_replace('!\s+!', ' ', trim($buffer));

                if ($buffer[0] != '#' && strlen($buffer) > 0) {
                    if (self::startsWith($buffer, '@import')) {
                        // @import <source> [<basedir>]
                        $tokens = explode(' ', $buffer);

                        array_push($config['imports'], array (
                            'source' =>  self::removeTrailingSlash($tokens[1]),
                            'basedir' => sizeof($tokens) == 3 ? self::removeTrailingSlash($tokens[2]) : null
                        ));
                    }
                    else if (self::startsWith($buffer, '@shell')) {
                        $command = substr($buffer, 7);
                        if ($command[strlen($command)-1] == '\\') {
                            $command = substr($command, 0, strlen($command) - 1);
                            array_push($config['shell_commands'], $command);

                            $keepGoing = true;
                            while ($keepGoing) {
                                $buffer = fgets($handle, 4096);
                                if ($buffer !== false) {
                                    $buffer = preg_replace('!\s+!', ' ', trim($buffer));

                                    if ($buffer[strlen($buffer)-1] == '\\') {
                                        array_push($config['shell_commands'], substr($buffer, 0, strlen($buffer) - 1));
                                    }
                                    else {
                                        array_push($config['shell_commands'], $buffer);
                                        $keepGoing = false;
                                    }
                                }
                            }
                        }
                        else {
                            array_push($config['shell_commands'], $command);
                        }
                    }
                    else {
                        // <link target> <link location>
                        $tokens = explode(' ', $buffer);
                        if (sizeof($tokens) > 2) continue;

                        $warningMsg = "should be a relative path. Absolute path detected.";

                        $src = self::removeTrailingSlash($tokens[0]);
                        if ($src[0] == '/') {
                            $subject = "link target";
                            $output->writeln("<comment>". self::getWarningFormattedText() . "Line $lineCounter: $subject $warningMsg</comment>");
                            $src = substr($src, 1, strlen($src)-1);
                        }

                        $dst = self::removeTrailingSlash($tokens[1]);
                        if ($dst[0] == '/') {
                            $subject = "link location";
                            $output->writeln("<comment>". self::getWarningFormattedText() . "Line $lineCounter: $subject $warningMsg</comment>");
                            $dst = substr($dst, 1, strlen($dst)-1);
                        }

                        if (self::endsWith($src, '/*')) {
                            $srcWithoutSlashStar = substr($src, 0, strlen($src)-2);
                            $files = scandir($modmanDir . self::PS . $srcWithoutSlashStar);
                            for ($i = 0; $i < sizeof($files); $i++) {
                                if ($files[$i] != '.' && $files[$i] != '..') {
                                    array_push($config['mapping'], array (
                                        "src" => $srcWithoutSlashStar . self::PS . $files[$i],
                                        "dst" => $dst . self::PS . $files[$i],
                                        "removal_path" => $dst . self::PS . $files[$i]
                                    ));
                                }
                            }
                        }
                        else {
                            array_push($config['mapping'], array (
                                "src" => $src,
                                "dst" => $dst,
                                "removal_path" => $dst
                            ));
                        }
                    }
                }

                $lineCounter++;
            }
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        }

        return $config;
    }

    public static function createRequiredDirectoryForPath($path, OutputInterface $output)
    {
        $path = self::removeTrailingSlash($path);
        $pos = strrpos($path, "/");
        if ($pos === false) {
            return null;
        }
        $pathWithoutFile = substr($path, 0, $pos);
        if ($pathWithoutFile) {
            if (!is_dir($pathWithoutFile)) {
                $output->writeln(self::getExtraInfoFormattedText() . "<extra_info>Creating required directory $pathWithoutFile</extra_info>");
                mkdir($pathWithoutFile, 0755, true);
            }
        }
    }

    public static function symlink($target, $link)
    {
        return symlink($target, $link);
        //return true;
    }

    public static function getAbsoluteSrcPath($args)
    {
        return self::removeDoubleSlash($args['path-specific-modman-dir']. self::PS . $args['src']);
    }

    public static function getAbsoluteDstPath($args)
    {
        $absDst = null;

        if ($args['subdir'] && !$args['path-specific-subdir']) {
            $absDst = getcwd() . self::PS . $args['subdir'] . self::PS . $args['dst'];
        }
        else if (!$args['subdir'] && $args['path-specific-subdir']) {
            $absDst = getcwd() . self::PS . $args['path-specific-subdir'] . self::PS . $args['dst'];
        }
        else if ($args['subdir'] && $args['path-specific-subdir']) {
            $absDst = getcwd() . self::PS . $args['subdir'] . self::PS . $args['path-specific-subdir'] . self::PS . $args['dst'];
        }
        else {
            $absDst = getcwd() . self::PS . $args['dst'];
        }

        if ($absDst) {
            return self::removeDoubleSlash($absDst);
        }
        else {
            return null;
        }
    }

    public static function getRelativePath($from, $to)
    {
        // some compatibility fixes for Windows paths
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
        $from = str_replace('\\', '/', $from);
        $to   = str_replace('\\', '/', $to);

        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }

    /**
     * Copy a file, or recursively copy a folder and its contents
     * @param       string   $source    Source path
     * @param       string   $dest      Destination path
     * @param       string   $permissions New folder creation permissions
     * @return      bool     Returns true on success, false on failure
     */
    public static function copyDir($source, $dest)
    {
        // Check for symlinks
        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }

        // Make destination directory
        if (!is_dir($dest)) {
            mkdir($dest);
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            self::copyDir("$source/$entry", "$dest/$entry");
        }

        // Clean up
        $dir->close();
        return true;
    }

    public static function deleteDir($dir)
    {
        if (is_dir($dir))
            $dir_handle = opendir($dir);
        if (!$dir_handle)
            return false;
        while($file = readdir($dir_handle)) {
            if ($file != "." && $file != "..") {
                if (!is_dir($dir."/".$file))
                    unlink($dir."/".$file);
                else
                    self::deleteDir($dir.'/'.$file);
            }
        }
        closedir($dir_handle);
        if (rmdir($dir)) return true;
        return false;
    }

    public static function removeTrailingSlash($path)
    {
        if (!$path) return null;
        if (strlen($path) <= 1) return null;

        if ($path[strlen($path) - 1] == self::PS) {
            return substr($path, 0, strlen($path) - 1);
        }

        return $path;
    }

    public static function removeDoubleSlash($path)
    {
        return str_replace('//', '/', $path);
    }

    public static function startsWith($haystack, $needle) {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
    }

    static public function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || strpos($haystack, $needle, strlen($haystack) - strlen($needle)) !== FALSE;
    }

    protected static function setStyles(OutputInterface $output)
    {
        $style = new OutputFormatterStyle('red');
        $output->getFormatter()->setStyle('fail', $style);

        $style = new OutputFormatterStyle('cyan');
        $output->getFormatter()->setStyle('details', $style);

        $style = new OutputFormatterStyle('magenta');
        $output->getFormatter()->setStyle('title', $style);

        $style = new OutputFormatterStyle('blue');
        $output->getFormatter()->setStyle('extra_info', $style);
    }

    public static function getSuccessFormattedText()
    {
        return "<info>" . str_pad("success", self::MSG_TEXT_PAD) . "</info>";
    }

    public static function getExtraInfoFormattedText()
    {
        return "<extra_info>" . str_pad("info", self::MSG_TEXT_PAD) . "</extra_info>";
    }

    public static function getWarningFormattedText()
    {
        return "<comment>" . str_pad("warning", self::MSG_TEXT_PAD) . "</comment>";
    }

    public static function getSkippedFormattedText()
    {
        return "<comment>" . str_pad("skipped", self::MSG_TEXT_PAD) . "</comment>";
    }

    public static function getFailedFormattedText()
    {
        return "<fail>" . str_pad("error", self::MSG_TEXT_PAD) . "</fail>";
    }

    public static function getDetailsFormattedText($text)
    {
        return "<details>(".$text.")</details>";
    }

    public static function getNoOperationFormattedText()
    {
        return "<extra_info>No operation was performed.</extra_info>";
    }
}
