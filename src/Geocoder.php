<?php

namespace Spatie\Geocoder;

use GuzzleHttp\Client;
use Spatie\Geocoder\Exceptions\CouldNotGeocode;

class Geocoder
{
    public const RESULT_NOT_FOUND = 'result_not_found';

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

    /** @var string */
    protected $bounds;

    /** @var string */
    protected $country;

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

    public function setBounds(string $bounds)
    {
        $this->bounds = $bounds;

        return $this;
    }

    public function setCountry(string $country)
    {
        $this->country = $country;

        return $this;
    }

    public function getCoordinatesForAddress(string $address): array
    {
        $response = $this->getAllCoordinatesForAddress($address);

        return $response[0];
    }

    public function getAllCoordinatesForAddress(string $address): array
    {
        if (empty($address)) {
            return $this->emptyResponse();
        }

        $payload = $this->getRequestPayload(compact('address'));
        $response = $this->client->request('GET', $this->endpoint, $payload);

        if ($response->getStatusCode() !== 200) {
            throw CouldNotGeocode::couldNotConnect();
        }

        $geocodingResponse = json_decode($response->getBody());

        if (! empty($geocodingResponse->error_message)) {
            throw CouldNotGeocode::serviceReturnedError($geocodingResponse->error_message);
        }

        if (! count($geocodingResponse->results)) {
            return $this->emptyResponse();
        }

        return $this->formatResponse($geocodingResponse);
    }

    public function getAddressForCoordinates(float $lat, float $lng): array
    {
        $response = $this->getAllAddressesForCoordinates($lat, $lng);

        return $response[0];
    }

    public function getAllAddressesForCoordinates(float $lat, float $lng): array
    {
        $payload = $this->getRequestPayload([
            'latlng' => "$lat,$lng",
        ]);

        $response = $this->client->request('GET', $this->endpoint, $payload);


        if ($response->getStatusCode() !== 200) {
            throw CouldNotGeocode::couldNotConnect();
        }

        $reverseGeocodingResponse = json_decode($response->getBody());

        if (! empty($reverseGeocodingResponse->error_message)) {
            throw CouldNotGeocode::serviceReturnedError($reverseGeocodingResponse->error_message);
        }

        if (! count($reverseGeocodingResponse->results)) {
            return $this->emptyResponse();
        }

        return $this->formatResponse($reverseGeocodingResponse);
    }

    protected function formatResponse($response): array
    {
        $locations = array_map(function ($result) {
            return [
                'lat' => $result->geometry->location->lat,
                'lng' => $result->geometry->location->lng,
                'accuracy' => $result->geometry->location_type,
                'formatted_address' => $result->formatted_address,
                'viewport' => $result->geometry->viewport,
                'address_components' => $result->address_components,
                'partial_match' => isset($result->partial_match) ? $result->partial_match : false ,
                'place_id' => $result->place_id,
                'types' => $result->types,
                'city' => $this->getCity($result->address_components),
//                'postal_code' => $this->getPostalCode($result->address_components),
//                'postal_code_suffix' => $this->getPostalCodeSuffic($result->address_components),
                'state' => $this->getState($result->address_components),
                'street_number' => $this->getStreetNumber($result->address_components),
                'street_name' => $this->getStreetName($result->address_components),
            ];
        }, $response->results);

        return $locations;
    }

    protected function getRequestPayload(array $parameters): array
    {
        $parameters = array_filter(array_merge([
            'key' => $this->apiKey,
            'language' => $this->language,
            'region' => $this->region,
            'bounds' => $this->bounds,
        ], $parameters));

        if ($this->country) {
            $parameters = array_merge(
                $parameters,
                ['components' => 'country:'.$this->country]
            );
        }

        return ['query' => $parameters];
    }

    protected function emptyResponse(): array
    {
        return [
            [
                'lat' => 0,
                'lng' => 0,
                'accuracy' => static::RESULT_NOT_FOUND,
                'formatted_address' => static::RESULT_NOT_FOUND,
                'viewport' => static::RESULT_NOT_FOUND,
            ],
        ];
    }

    protected function getCity(array $addressComponents): ?string {
        return $this->getAddressComponentByType($addressComponents, 'postal_code');
    }

    protected function getState(array $addressComponents): ?string {
        return $this->getAddressComponentByType($addressComponents, 'administrative_area_level_1');
    }


    protected function getStreetNumber(array $addressComponents): ?string {
        return $this->getAddressComponentByType($addressComponents, 'street_number');
    }

    protected function getStreetName(array $addressComponents): ?string {
        return $this->getAddressComponentByType($addressComponents, 'route');
    }

    protected function getPostalCode(array $addressComponents): ?string {
        return $this->getAddressComponentByType($addressComponents, 'postal_code');
    }

    protected function getPostalCodeSuffic(array $addressComponents): ?string {
        return $this->getAddressComponentByType($addressComponents, 'postal_code_suffix');
    }

    protected function getAddressComponentByType(array $addressComponents, string $componentName): ?string {
        $key = null;
        foreach ($addressComponents as $k => $component) {
            if (in_array($componentName, $component->types)) {
                $key = $k;
                break;
            }
        }

        return $key ? $addressComponents[$k]->short_name : null;
    }

}
