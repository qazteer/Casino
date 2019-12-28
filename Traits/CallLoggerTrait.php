<?php

namespace App\Traits;

use App\Models\EndpointsLog;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait CallLoggerTrait {

    /**
     * Called to log outbound requests
     * Can't catch route param, so we don't have one here
     *
     * @param string $provider
     * @param string $method
     * @param string $url
     * @param array|string $headers
     * @param array|string $body
     *
     * @return int|array
     */
    public function logRequest($provider, $method, $url, $headers = '', $body = '')
    {
        try {
            $log = EndpointsLog::create([
                'url'      => $url,
                'type'     => EndpointsLog::TYPES[1],
                'method'   => $method,
                'provider' => $provider,
                'ip'       => request()->ip(),
                'route'    => '',
                'headers'  => is_string($headers) ? $headers : json_encode($headers),
                'body'     => is_string($body) ? $body : json_encode($body),
                'response' => '',
            ]);
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }

        return $log->id;
    }

    /**
     * @param int $id
     * @param string $response
     *
     * @return array|bool
     */
    public function logResponse($id, $response = '')
    {
        try {
            EndpointsLog::updateOrCreate([
                'id'       => $id,
            ], [
                'response' => is_string($response) ? $response : json_encode($response),
            ]);
        } catch (ModelNotFoundException $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }

        return true;
    }
}
