<?php

namespace Spatie\Geocoder;

use Exception;
use GuzzleHttp\Client;

class Geocoder
{
    const RESULT_NOT_FOUND = 'result_not_found';

    /** @var \GuzzleHttp\Client */
    protected $client;

    /** @var string */
    protected $endpoint = 'https://maps.googleapis.com/maps/api/geocode/json';

    /** @var string */
    protected $apiKey;

    /** @var string */
    protected $language;

    /** @var string */
    protected $region;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function setApiKey(string $apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function setLanguage(string $language)
    {
        $this->language = $language;

        return $this;
    }

    public function setRegion(string $region)
    {
        $this->region = $region;

        return $this;
    }

    public function getCoordinatesForAddress(string $address): array
    {
        if (empty($address)) {
            return $this->emptyResponse();
        }

        $payload = $this->getRequestPayload(compact('address'));

        $response = $this->client->request('GET', $this->endpoint, $payload);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('could not connect to googleapis.com/maps/api');
        }

        $geocodingResponse = json_decode($response->getBody());

        if (! count($geocodingResponse->results)) {
            return $this->emptyResponse();
        }

        return $this->formatResponse($geocodingResponse);
    }

    public function getAddressForCoordinates(float $lat, float $lng): array
    {
        $payload = $this->getRequestPayload([
            'latlng' => "$lat,$lng",
        ]);

        $response = $this->client->request('GET', $this->endpoint, $payload);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('could not connect to googleapis.com/maps/api');
        }

        $reverseGeocodingResponse = json_decode($response->getBody());

        if (! count($reverseGeocodingResponse->results)) {
            return $this->emptyResponse();
        }

        return $this->formatResponse($reverseGeocodingResponse);
    }

    protected function formatResponse($response): array
    {
        $address_components = $response->results[0]->address_components;

        return [
            'lat' => $response->results[0]->geometry->location->lat,
            'lng' => $response->results[0]->geometry->location->lng,
            'accuracy' => $response->results[0]->geometry->location_type,
            'street_number' => $this->getAddressComponent($address_components, 'street_number', 'long_name'),
            'route' => $this->getAddressComponent($address_components, 'route'),
            'city' => $this->getAddressComponent($address_components, 'locality', 'long_name'),
            'state' => $this->getAddressComponent($address_components, 'administrative_area_level_1'),
            'country' => $this->getAddressComponent($address_components, 'country'),
            'postal_code' => $this->getAddressComponent($address_components, 'postal_code'),
            'formatted_address' => $response->results[0]->formatted_address,
        ];
    }

    protected function getAddressComponent($address_components, $key, $attribute = 'short_name')
    {
      foreach ($address_components as $component) {

        if (in_array($key, $component->types)) {
          return $component->$attribute;
        }

      }

      return false;
    }

    protected function getRequestPayload(array $parameters): array
    {
        $parameters = array_merge([
            'key' => $this->apiKey,
            'language' => $this->language,
            'region' => $this->region,
        ], $parameters);

        return ['query' => $parameters];
    }

    protected function emptyResponse(): array
    {
        return [
            'lat' => 0,
            'lng' => 0,
            'accuracy' => static::RESULT_NOT_FOUND,
            'formatted_address' => static::RESULT_NOT_FOUND,
        ];
    }
}
