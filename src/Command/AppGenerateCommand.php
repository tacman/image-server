<?php

namespace App\Command;

use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;

#[AsCommand('app:generate', '(re-)generate images from database')]
final class AppGenerateCommand extends InvokableServiceCommand
{
    use RunsCommands;
    use RunsProcesses;

    public function __invoke(
        IO $io,
        MediaRepository $mediaRepository,
        MessageBusInterface $bus,

        #[Option(description: 'filter by a specific path')]
        string $path = '',
    ): int {

        $files = $mediaRepository->findAll();
        $progressBar = new ProgressBar($io, $mediaRepository->count());
        foreach ($files as $media) {
            $progressBar->advance();
            foreach ($media->getThumbData()??[] as $filter=> $currentSize) {
                $bus->dispatch(new ResizeImageMessage($filter, $media->getPath(), code: $media->getCode()));
            }
        }
        $progressBar->finish();
        $io->success($this->getName().' success.');
        return self::SUCCESS;
    }
}
