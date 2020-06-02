<?php

namespace App\Http\Resources;

use App\Models\Provider;
use App\Models\User;
use App\Traits\GlobalTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class SingleDoctorResource extends JsonResource
{

    use GlobalTrait;
    public function toArray($request)
    {

        return request()->api_token;
          $authUser = $this->auth('user-api');
        if (!$authUser)
            $user = null;
        else
            $user = User::find($authUser->id);

        $result = [
            'id' => $this->id,
            'doctor_type' => $this->doctor_type,
            'name' => app()->getLocale() == 'ar' ? $this->name_ar : $this->name_en,
            'information' => app()->getLocale() == 'ar' ? $this->information_ar : $this->information_en,
            'abbreviation' => app()->getLocale() == 'ar' ? $this->abbreviation_ar : $this->abbreviation_en,
            'branch' => app()->getLocale() == 'ar' ? isset($this->provider->name_ar )? $this->provider->name_ar :""  : isset($this->provider->name_en )? $this->provider->name_en :"" ,
            'nickname' => [
                'id' => $this->nickname->id,
                'name' => app()->getLocale() == 'ar' ? $this->nickname->name_ar : $this->nickname->name_en,
            ],
            'specification' => [
                'id' => $this->specification->id,
                'name' => app()->getLocale() == 'ar' ? $this->specification->name_ar : $this->specification->name_en
            ],
            'price' => $this->price,
            'rate' => $this->rate,
            'photo' => $this->photo,
            'favourite' => $user==null ? 0 : ($user->favourites()->where('doctor_id', $this->id)->first() == null ? 0 : 1),
        ];

        if (!empty($this->provider)) {
            $mainProvider = Provider::whereNull('provider_id')->find($this->provider->provider_id);
            $result['provider'] = $mainProvider ? (app()->getLocale() == 'ar' ? $mainProvider->name_ar : $mainProvider->name_en) : null;
        } else {
            $result['provider'] = null;
        }

        $res = !is_null($this->consultativeTimes) && count($this->consultativeTimes) > 0 ? $this->consultativeTimes() : $this->times();
        $res = $res->distinct()->get(['day_code', 'day_name'])->makeHidden(['time_duration']);
        $result['times'] = $res;

        return $result;
    }

}
