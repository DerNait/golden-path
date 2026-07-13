<?php

namespace App\Http\Resources;

use App\Support\YouTubeUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'slug'=>$this->slug,
            'description'=>$this->description,
            'instructions'=>$this->instructions,
            'equipment'=>$this->equipment,
            'image_url'=>$this->image_path ? asset('storage/'.$this->image_path) : null,
            'youtube_url'=>$this->youtube_url,
            'youtube_embed_url'=>YouTubeUrl::embed($this->youtube_url),
            'metric_type'=>$this->metric_type,
            'weight_mode'=>$this->weight_mode,
            'default_weight_unit'=>$this->default_weight_unit,
            'default_increment'=>$this->default_increment,
            'is_active'=>$this->is_active,
            'muscle_groups'=>$this->whenLoaded('muscleGroups'),
            'alternative_relation_id'=>$this->pivot?->id,
            'alternatives'=>ExerciseResource::collection($this->whenLoaded('alternativeExercises')),
        ];
    }
}
