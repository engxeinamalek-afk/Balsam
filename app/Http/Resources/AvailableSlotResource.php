<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startTime = Carbon::createFromFormat('H:i', $this->resource);
        $endTime = $startTime->copy()->addMinutes(30);

        return [
            'start_time' => $startTime->format('H:i'),
            'end_time'   => $endTime->format('H:i'),
        ];
    }
}
