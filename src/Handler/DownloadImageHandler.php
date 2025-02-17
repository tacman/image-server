<?php

namespace App\Handler;

use App\Entity\Thumb;
use App\Message\DownloadImage;
use App\Message\ResizeImageMessage;
use App\Repository\MediaRepository;
use App\Repository\ThumbRepository;
use App\Service\ApiService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Survos\SaisBundle\Service\SaisClientService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thumbhash\Thumbhash;
use function Symfony\Component\String\u;

class DownloadImageHandler
{

    public function __construct(
    )
    {
    }

    #[AsMessageHandler()]
    public function onDownloadImage(DownloadImage $message): void
    {

    }



}
