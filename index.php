<?php

require __DIR__.'/vendor/autoload.php';

use Dflydev\ApacheMimeTypes\PhpRepository;
use Dflydev\ApacheMimeTypes\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

$resolver = new OptionsResolver();
$resolver->setDefined([
    'FEED_ROOT',
    'FEED_MIMES',
    'FEED_MAX',
    'FEED_NAME',
    'FEED_DESCRIPTION',
    'FEED_URL',
    'FEED_BASE_URL',
]);
$resolver->setRequired([
    'FEED_URL',
]);
$resolver->setDefaults([
    'FEED_ROOT' => '/srv/files',
    'FEED_MIMES' => 'video/*, audio/*',
    'FEED_MAX' => 50,
    'FEED_NAME' => 'AutoFeed RSS',
    'FEED_DESCRIPTION' => 'AutoFeed RSS',
    'FEED_BASE_URL' => function (Options $options) {
        return rtrim($options['FEED_URL'], '/').'/';
    },
]);
$resolver->setNormalizer('FEED_MIMES', function (Options $options, $value) {
    return array_map('trim', explode(',', $value));
});
$resolver->setNormalizer('FEED_MAX', function (Options $options, $value) {
    return intval($value);
});

$options = [];
foreach ($resolver->getDefinedOptions() as $key) {
    if (false !== $value = getenv($key, true)) {
        $options[$key] = $value;
    }
}

$options = $resolver->resolve($options);

function scan(string $directory, array &$paths, array $allowedMimes, RepositoryInterface $mimeTypeRepository)
{
    $files = scandir($directory);
    foreach ($files as $filename) {
        if (in_array($filename, ['.', '..'])) {
            continue;
        }

        $path = $directory.'/'.$filename;

        if (!is_readable($path)) {
            continue;
        }

        if (is_dir($path)) {
            scan($path, $paths, $allowedMimes, $mimeTypeRepository);
            continue;
        }

        $mime = $mimeTypeRepository->findType(pathinfo($path, PATHINFO_EXTENSION));

        foreach ($allowedMimes as $pattern) {
            if (fnmatch($pattern, $mime)) {
                $paths[] = [
                    'path' => $path,
                    'timestamp' => filemtime($path),
                ];
                break;
            }
        }
    }
}

$paths = [];

scan($options['FEED_ROOT'], $paths, $options['FEED_MIMES'], new PhpRepository());

usort($paths, function ($a, $b) {
    $at = $a['timestamp'];
    $bt = $b['timestamp'];
    return $at == $bt ? 0 : ($at > $bt ? -1 : 1);
});

$paths = array_slice($paths, 0, $options['FEED_MAX']);

$xml = new SimpleXMLElement('<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"/>');
$channel = $xml->addChild('channel');
$channel->addChild('title', $options['FEED_NAME']);
$channel->addChild('link', $options['FEED_URL']);
$channel->addChild('description', $options['FEED_DESCRIPTION']);

foreach ($paths as $row) {
    $title = pathinfo($row['path'], PATHINFO_BASENAME);
    $path = substr($row['path'], strlen($options['FEED_ROOT']) + 1);
    $link = sprintf('%s%s', $options['FEED_BASE_URL'], $path);

    $item = $channel->addChild('item');
    $item->addChild('title', $title);
    $item->addChild('link', $link);
    $item->addChild('guid', $link);
}

Header('Content-type: text/xml');
echo $xml->asXML();
