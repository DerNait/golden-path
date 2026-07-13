<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class RecommendationResource extends JsonResource
{
    public function toArray(Request $request): array { return array_merge(parent::toArray($request), ['exercise'=>$this->whenLoaded('exercise')]); }
}
