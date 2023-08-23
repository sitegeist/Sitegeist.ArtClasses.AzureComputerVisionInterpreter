<?php

/*
 * This file is part of the Sitegeist.ArtClasses package.
 */

declare(strict_types=1);

namespace Sitegeist\ArtClasses\AzureComputerVisionInterpreter\Domain;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\I18n\Locale;
use Neos\Media\Domain\Model\Image;
use Psr\Http\Message\UriInterface;
use Sitegeist\ArtClasses\Domain\Interpretation\ImageInterpreterInterface;
use Sitegeist\ArtClasses\Domain\Interpretation\ImageInterpretation;

/**
 * An Azure Computer Vision based interpreter
 */
final class AzureComputerVisionInterpreter implements ImageInterpreterInterface
{
    public function __construct(
        private readonly UriInterface $endpointBaseUri,
        private readonly string $subscriptionKey
    ) {
    }

    public function interpretImage(Image $image, ?Locale $targetLocale): ImageInterpretation
    {
        $response = $this->sendRequest($image, $targetLocale);
        $message = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() === 400 && $message['error']['innererror']['code'] ?? null === 'NotSupportedLanguage') {
            $response = $this->sendRequest($image, null);
            $message = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        }

        \Neos\Flow\var_dump($message);
        exit();
    }

    private function sendRequest(Image $image, ?Locale $targetLocale): Response
    {
        $endpointUri = $this->endpointBaseUri
            ->withPath('computervision/imageanalysis:analyze')
            ->withQuery(http_build_query([
                'api-version' => '2023-04-01-preview',
                'features' => 'caption',
                'language' => $targetLocale?->getLanguage() ?: 'en',
                'gender-neutral-caption' => 'true',
            ]));

        $curlEngine = new CurlEngine();

        return $curlEngine->sendRequest(new Request(
            'POST',
            $endpointUri,
            [
                'Content-Type' => $image->getResource()->getMediaType(),
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ],
            stream_get_contents($image->getResource()->getStream())
        ));
    }
}
