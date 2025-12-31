<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    /**
     * Customize the response to always follow { data, meta } shape.
     */
    public function toResponse($request)
    {
        $data = $this->resolve();
        $meta = [
            'success' => true,
            'code' => 200,
        ];

        return response()->json(['data' => $data, 'meta' => $meta], 200);
    }
}
