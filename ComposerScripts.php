<?php

namespace Blaupause;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Locker;
use Composer\Script\Event;

class ComposerScripts
{
    public static function postCreateProject(Event $event)
    {
        $args = $event->getArguments();

        $io = $event->getIO();

        $projectName = $args['directory'] ?? 'blaupause';
        $projectName = $io->ask('Enter the project identifier [<comment>' . $projectName . '</comment>]: ', $projectName);

        self::replace(__DIR__ . '/.distignore', $projectName);
        self::replace(__DIR__ . '/.gitattributes', $projectName);
        self::replace(__DIR__ . '/.gitignore', $projectName);
        self::replace(__DIR__ . '/README.md.template', $projectName);

        self::replaceDir(__DIR__ . '/public', $projectName);

        unlink(__DIR__ . '/LICENSE');

        unlink(__DIR__ . '/README.md');
        rename(__DIR__ . '/README.md.template', __DIR__ . '/README.md');

        // ---

        $manipulator = new JsonManipulator(
            file_get_contents(__DIR__ . '/composer.json')
        );

        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');
        $manipulator->removeProperty('keywords');
        $manipulator->removeProperty('homepage');
        $manipulator->removeProperty('license');
        $manipulator->removeSubNode('require-dev', 'composer/composer');
        $manipulator->removeProperty('autoload');
        $manipulator->removeSubNode('scripts', 'post-create-project-cmd');

        file_put_contents(
            __DIR__ . '/composer.json',
            $manipulator->getContents()
        );

        self::updateComposerLock(
            __DIR__ . '/composer.json',
            $event->getComposer(),
            $io
        );

        // ---

        unlink(__DIR__ . '/ComposerScripts.php');
    }

    private static function replaceDir($dir, $projectName)
    {
        foreach (glob(rtrim($dir, '\\/') . '/*') as $file) {
            if (is_dir($file)) {
                self::replaceDir($file, $projectName);
            } else {
                self::replace($file, $projectName);
            }
        }
    }

    private static function replace($file, $projectName)
    {
        $content = file_get_contents($file);

        $content = str_ireplace(
            ['blaupause'],
            $projectName,
            $content
        );

        file_put_contents($file, $content);
    }

    private static function updateComposerLock($composerFile, Composer $composer, IOInterface $io)
    {
        $lock = substr($composerFile, 0, -4) . 'lock';
        $composerJson = file_get_contents($composerFile);
        $lockFile = new JsonFile($lock, null, $io);
        $locker = new Locker(
            $io,
            $lockFile,
            $composer->getRepositoryManager(),
            $composer->getInstallationManager(),
            $composerJson
        );
        $lockData = $locker->getLockData();
        $lockData['content-hash'] = Locker::getContentHash($composerJson);
        $lockFile->write($lockData);
    }
}
