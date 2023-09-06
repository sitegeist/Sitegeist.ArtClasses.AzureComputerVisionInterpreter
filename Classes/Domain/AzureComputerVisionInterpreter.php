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
use Sitegeist\ArtClasses\Domain\Interpretation\InterpretedBoundingPolygon;
use Sitegeist\ArtClasses\Domain\Interpretation\InterpretedText;
use Sitegeist\ArtClasses\Domain\Interpretation\InterpretedVertex;

/**
 * An Azure Computer Vision based interpreter
 */
final class AzureComputerVisionInterpreter implements ImageInterpreterInterface
{
    public function __construct(
        private readonly UriInterface $endpointBaseUri,
        private readonly string $subscriptionKey,
        private readonly float $minimumScore
    ) {
    }

    public function interpretImage(Image $image, ?Locale $targetLocale): ImageInterpretation
    {
        $usedTargetLocale = $targetLocale;
        $response = $this->sendRequest($image, $usedTargetLocale);
        $message = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() === 400 && $message['error']['innererror']['code'] ?? null === 'NotSupportedLanguage') {
            $usedTargetLocale = new Locale('en');
            $response = $this->sendRequest($image, $usedTargetLocale);
            $message = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        }

        if ($response->getStatusCode() !== 200) {
            // @todo: log and return empty interpretation
            throw new \RuntimeException(
                'Could not interpret image: "'
                    . ($message['error']['innererror']['message'] ?? $message['error']['message'] ?? 'unknown error') . '"',
                1693214374
            );
        }

        $description = null;
        if ($message['captionResult']['confidence'] > $this->minimumScore) {
            $description = $message['captionResult']['text'];
        }

        $texts = [];
        foreach ($message['readResult']['pages'] as $page) {
            foreach ($page['lines'] as $line) {
                $texts[] = new InterpretedText(
                    $line['content'],
                    null,
                    new InterpretedBoundingPolygon(
                        [
                            new InterpretedVertex(
                                (int)$line['boundingBox'][0],
                                (int)$line['boundingBox'][1]
                            ),
                            new InterpretedVertex(
                                (int)$line['boundingBox'][2],
                                (int)$line['boundingBox'][3]
                            ),
                            new InterpretedVertex(
                                (int)$line['boundingBox'][4],
                                (int)$line['boundingBox'][5]
                            ),
                            new InterpretedVertex(
                                (int)$line['boundingBox'][6],
                                (int)$line['boundingBox'][7]
                            ),
                        ],
                        []
                    ),
                );
            }
        }

        return new ImageInterpretation(
            $usedTargetLocale,
            $description,
            array_filter(array_map(
                fn (array $tagResult): ?string => $tagResult['confidence'] > $this->minimumScore ? $tagResult['name'] : null,
                $message['tagsResult']['values'] ?? []
            )),
            [],
            $texts,
            [],
            array_map(
                fn (array $smartCropResult): InterpretedBoundingPolygon => new InterpretedBoundingPolygon(
                    [
                        new InterpretedVertex(
                            $smartCropResult['boundingBox']['x'],
                            $smartCropResult['boundingBox']['y']
                        ),
                        new InterpretedVertex(
                            $smartCropResult['boundingBox']['x'],
                            $smartCropResult['boundingBox']['y'] + $smartCropResult['boundingBox']['h']
                        ),
                        new InterpretedVertex(
                            $smartCropResult['boundingBox']['x'] + $smartCropResult['boundingBox']['w'],
                            $smartCropResult['boundingBox']['y'] + $smartCropResult['boundingBox']['h']
                        ),
                        new InterpretedVertex(
                            $smartCropResult['boundingBox']['x'] + $smartCropResult['boundingBox']['w'],
                            $smartCropResult['boundingBox']['y']
                        ),
                    ],
                    [],
                ),
                $message['smartCropsResult']['values']
            )
        );
    }

    private function sendRequest(Image $image, ?Locale $targetLocale): Response
    {
        $endpointUri = $this->endpointBaseUri
            ->withPath('computervision/imageanalysis:analyze')
            ->withQuery(http_build_query([
                'api-version' => '2023-04-01-preview',
                'features' => 'caption,tags,objects,read,smartCrops',
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
