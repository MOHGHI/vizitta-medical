<?php

namespace App\Traits;

use App\Models\Doctor;
use App\Models\Message;
use App\Models\ServiceReservation;
use App\Models\Ticket;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Reservation;
use Carbon\Carbon;
use DateTime;
use DB;
use Illuminate\Support\Facades\Auth;

trait ProviderTrait
{
    public function findProvider($id)
    {
        return Provider::find($id);
    }

    public function checkDoctorInBranch($doctor_id, $branch_id)
    {
        $doctorInBranch = Doctor::where('provider_id', $branch_id)->where('id', $doctor_id)->first();
        if ($doctorInBranch == null)
            return false;

        return true;
    }

    public function getProviderByID($id)
    {
        $provider = Provider::query()->with(['type' => function ($q) {
            $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'city' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'district' => function ($qqq) {
            $qqq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'main_provider' => function ($qqqq) {
            $qqqq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }

        ])->select(['id', DB::raw('name_' . app()->getLocale() . ' as name'),
            'logo', 'rate', 'longitude', 'latitude', 'provider_id', 'status', 'type_id', 'city_id', 'district_id', 'email', 'address', 'street', 'name_ar', 'name_en']);
        $provider = $provider->find($id);
        return $provider;
    }


    public function getData($token)
    {

        $provider = Provider::whereHas('tokens', function ($q) use ($token) {
            $q->where('api_token', $token);
        })
            ->with(['type' => function ($q) {
                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'city' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'district' => function ($que) {
                $que->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }])->first();

        if ($provider != null)
            return $provider->makeVisible(['api_token', 'activation_code']);

        return $provider;
    }


    public function getDataByLastToken($token)
    {

        $provider = Provider::where('api_token', $token)
            ->with(['type' => function ($q) {
                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'city' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'district' => function ($que) {
                $que->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }])->first();

        if ($provider != null)
            return $provider->makeVisible(['api_token', 'activation_code']);

        return $provider;
    }

    public function getAllData($token, $activation = false)
    {
        $provider = Provider::whereHas('tokens', function ($q) use ($token) {
            $q->where('api_token', $token);
        })
            ->with(['type' => function ($q) {
                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'city' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'district' => function ($que) {
                $que->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }])->first()->makeVisible(['api_token']);
        if ($activation)
            $provider->makeVisible('activation_code');
        else
            $provider->makeHidden('activation_code');

        return $provider;
    }

    public function getProviderByMobileInUpdate($id, $mobile)
    {
        $provider = Provider::where('id', '!=', $id)->where('mobile', $mobile)->first();
        if ($provider == null) {
            if (preg_match("~^0\d+$~", $mobile)) {
                $mobile = substr($mobile, 1);
            } else {
                $mobile = '0' . $mobile;
            }
            $provider = Provider::where('id', '!=', $id)->where('mobile', $mobile)->first();
        }
        return $provider;
    }

    public function getProviderByMobileOrEmailOrID($mobile = '', $email = '', $id_number = '', $type = 1)
    {
        if (empty($mobile) && empty($email))
            return null;
        $user = Provider::query();
        if ($type == 1) {
            $user->where('provider_id', null);
        }
        if ($type == 0) {
            $user->whereNotNull('provider_id');
        }
        if (!empty($id_number))
            $user->Where('id_number', $id_number);
        if (!empty($mobile)) {
            $user->Where('mobile', $mobile);
        }
        if (!empty($email))
            $user->Where('email', $email);
        return $user->first();
    }

    public function getProviderByMobile($mobile)
    {
        $provider = Provider::where('mobile', $mobile)->first();
        if ($provider == null) {
            if (preg_match("~^0\d+$~", $mobile)) {
                $mobile = substr($mobile, 1);
            } else {
                $mobile = '0' . $mobile;
            }
            $provider = Provider::where('mobile', $mobile)->first();
        }
        if ($provider != null)
            return $provider->makeVisible(['api_token']);

        return $provider;
    }

    public function getProviderByEmail($email)
    {
        return Provider::where('email', $email)->first();
    }


    public function authProviderByMobile($mobile, $password, $type = 1)
    {
        $providerId = null;
        if ($type == 1) // main provider
        {
            $provider = Provider::where('mobile', $mobile)->where('provider_id', null)->first();
            $providerId = null; // main provider
        } else {
            $provider = Provider::where('mobile', $mobile)->whereNotNull('provider_id')->first();
            if (!$provider) {
                return null;
            }
            $providerId = $provider->provider_id;
        }

        $token = Auth::guard('provider-api')->attempt(['mobile' => $mobile, 'password' => $password, 'provider_id' => $providerId]);
        //$token = Auth::guard('provider-api') ->tokenById($provider->id);
        if (!$provider)
            return null;

        // to allow open  app on more device with the same account
        if ($token) {
            $newToken = new \App\Models\Token(['provider_id' => $provider->id, 'api_token' => $token]);
            $provider->tokens()->save($newToken);
            //last access token
            $provider->update(['api_token' => $token]);
            return $provider;
        }

        if (preg_match("~^0\d+$~", $mobile)) {
            $mobile = substr($mobile, 1);
        } else {
            $mobile = '0' . $mobile;
        }

        $provider = Provider::where('mobile', $mobile)->first();
        $token = Auth::guard('provider-api')->attempt(['mobile' => $mobile, 'password' => $password, 'provider_id' => $providerId]);

        // to allow open  app on more device with the same account

        if ($token) {
            $newToken = new \App\Models\Token(['provider_id' => $provider->id, 'api_token' => $token]);
            $provider->tokens()->save($newToken);
            $provider->update(['api_token' => $token]);
            return $provider;
        }
        return null;
    }


    public function authProviderByUserName($username, $password, $type = 1)
    {

        if ($type == 1) // main provider
        {
            $provider = Provider::where('username', $username)->where('provider_id', null)->first();
            $providerId = null; // main provider
        } else {
            $provider = Provider::where('username', $username)->whereNotNull('provider_id')->first();
            if (!$provider) {
                return null;
            }
            $providerId = $provider->provider_id;
        }

        $token = Auth::guard('provider-api')->attempt(['username' => $username, 'password' => $password, 'provider_id' => $providerId]);
        //$token = Auth::guard('provider-api') ->tokenById($provider->id);
        if (!$provider)
            return null;

        // to allow open  app on more device with the same account
        if ($token) {
            $newToken = new \App\Models\Token(['provider_id' => $provider->id, 'api_token' => $token]);
            $provider->tokens()->save($newToken);
            //last access token
            $provider->update(['api_token' => $token]);
            return $provider;
        }

        return null;
    }


    public function checkIfMobileExistsForOtherBranches($mobile)
    {
        $exists = Provider::whereNotNull('provider_id')->where('mobile', $mobile)->first();
        if ($exists) {
            return true;
        }
        return false;
    }

    public function checkIfUserNameExistsForOtherBranches($username)
    {
        $exists = Provider::whereNotNull('provider_id')->where('username', $username)->first();
        if ($exists) {
            return true;
        }
        return false;
    }


    public function checkIfMobileExistsForOtherProviders($mobile)
    {
        $exists = Provider::where('provider_id', null)->where('mobile', $mobile)->first();
        if ($exists) {
            return true;
        }
        return false;
    }

    public function authProviderByEmail($email, $password)
    {
        $provider = Provider::where('email', $email)->first();
        $token = Auth::guard('provider-api')->attempt(['email' => $email, 'password' => $password]);

        if ($token) {
            $newToken = new \App\Models\Token(['provider_id' => $provider->id, 'api_token' => $token]);
            $provider->tokens()->save($newToken);
            $provider->update(['api_token' => $token]);
            return $provider;
        }
        return null;
    }

    public function getDoctors($IDs, $specificationId = null, $nicknameId = null, $branchId = null, $gender = null, $front = 0)
    {
        $doctor = Doctor::query();
        $doctor = $doctor->whereIn('provider_id', $IDs)
            ->with(['specification' => function ($q1) {
                $q1->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
            },// 'times' => function($q){
                // $q->orderBy('order');
                //},
                'nationality' => function ($q2) {
                    $q2->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'insuranceCompanies' => function ($q2) {
                    $q2->select('insurance_companies.id', 'image', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'nickname' => function ($q3) {
                    $q3->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        if ($specificationId != null && $specificationId != 0)
            $doctor = $doctor->where('specification_id', $specificationId);

        if ($nicknameId != null && $nicknameId != 0)
            $doctor = $doctor->where('nickname_id', $nicknameId);

        if ($branchId != null && $branchId != 0)
            $doctor = $doctor->where('provider_id', $branchId);

        if ($gender != null && $gender != 0 && in_array($gender, [1, 2]))
            $doctor = $doctor->where('gender', $gender);

        $doctor = $doctor->select('id', 'specification_id',
            'is_consult','doctor_type','nationality_id', 'nickname_id', 'photo', 'gender', 'rate', 'price', 'status',
            DB::raw('name_' . $this->getCurrentLang() . ' as name'),
            DB::raw('information_' . $this->getCurrentLang() . ' as information'),
            DB::raw('abbreviation_' . $this->getCurrentLang() . ' as abbreviation')
        );

        // not check doctor status if api visit by front-end dev
        if ($front == 1) {
            return $doctor->paginate(10);
        } else {
            return $doctor->where('doctors.status', 1)->paginate(10);
        }

    }

    public function getDoctorsV2($IDs, $specificationId = null, $nicknameId = null, $branchId = null, $gender = null, $front = 0, $doctor_name = '')
    {
        $doctor = Doctor::query();
        $doctor = $doctor->whereIn('provider_id', $IDs)
            ->with(['specification' => function ($q1) {
                $q1->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
            },// 'times' => function($q){
                // $q->orderBy('order');
                //},
                'nationality' => function ($q2) {
                    $q2->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'insuranceCompanies' => function ($q2) {
                    $q2->select('insurance_companies.id', 'image', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'nickname' => function ($q3) {
                    $q3->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);

        if ($specificationId != null && $specificationId != 0)
            $doctor = $doctor->where('specification_id', $specificationId);

        if ($nicknameId != null && $nicknameId != 0)
            $doctor = $doctor->where('nickname_id', $nicknameId);

        /* if ($branchId != null && $branchId != 0)
             $doctor = $doctor->where('provider_id', $branchId);*/

        if ($gender != null && $gender != 0 && in_array($gender, [1, 2]))
            $doctor = $doctor->where('gender', $gender);

        if (isset($doctor_name) && !empty($doctor_name)) {
            $doctor = $doctor->where(function ($qq) use ($doctor_name) {
                $qq->where('name_en', 'LIKE', '%' . trim($doctor_name) . '%')
                    ->orWhere('name_ar', 'LIKE', '%' . trim($doctor_name) . '%');
            });
        }

        $doctor = $doctor->select('id', 'specification_id',
            'nationality_id', 'nickname_id','is_consult','photo', 'gender', 'rate', 'price', 'status', 'waiting_period',
            DB::raw('name_' . $this->getCurrentLang() . ' as name'),
            DB::raw('information_' . $this->getCurrentLang() . ' as information'),
            DB::raw('abbreviation_' . $this->getCurrentLang() . ' as abbreviation')
        );

        // not check doctor status if api visit by front-end dev
        if ($front == 1) {
            return $doctor->paginate(10);
        } else {
            return $doctor->where('doctors.status', 1)->paginate(10);
        }

    }

    public function getAllProviders($userId = null, $longitude = null, $latitude = null, $order = "ASC", $rate = 0, $type_id = [])
    {
        $provider = Provider::query();
        $provider = $provider->with(['type' => function ($q) {
            $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
        }, 'favourites' => function ($qu) use ($userId) {
            $qu->where('user_id', $userId)->select('provider_id');
        }, 'city' => function ($q) {
            $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
        }, 'district' => function ($q) {
            $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
        }])->whereHas('providers');
        //$provider = $provider->get();
        if (is_array($type_id) && count($type_id) > 0) {
            $provider = $provider->whereIn('type_id', $type_id);
        }
        if ($longitude != null && !empty($longitude) && $latitude != null && !empty($latitude)) {
            $provider = $provider->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'),
                DB::raw('(3959 * acos(cos(radians(' . $latitude . ')) * cos(radians(latitude)) * cos(radians(longitude) - radians(' . $longitude . ')) + sin(radians(' . $latitude . ')) * sin(radians(latitude)))) AS distance'));
            if ($rate == 1) {
                $provider = $provider->orderBy('rate', 'DESC')->orderBy('distance', $order);
            } else {
                $provider = $provider->orderBy('distance', $order);
            }
        } else {
            $provider = $provider->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'), DB::raw("'0' as distance"));
            if ($rate == 1) {
                $provider = $provider->orderBy('rate', 'DESC');
            }
        }
        return $provider->paginate(10);
    }

//DB::raw('RAND()')
    public function getProvidersFeaturedBranch($userId = null, $longitude = null, $latitude = null, $specification_id = 0)
    {
        $provider = Provider::query();
        $provider = $provider->whereHas('subscriptions')
            ->whereHas('doctors')
            ->with(['subscriptions' => function ($sub) {
                $sub->select('id', 'branch_id', 'duration', 'created_at');
            },
                'type' => function ($q) {
                    $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                }, 'doctors', 'favourites' => function ($qu) use ($userId) {
                    $qu->where('user_id', $userId)->select('provider_id');
                }, 'city' => function ($q) {
                    $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                }, 'district' => function ($q) {
                    $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                }])->where('provider_id', '!=', null);

        if (isset($specification_id) && $specification_id != 0) {
            $provider = $provider->whereHas('doctors', function ($query) use ($specification_id) {
                $query->where('specification_id', $specification_id);
            });
        }


        if ($longitude != null && !empty($longitude) && $latitude != null && !empty($latitude)) {
            $provider = $provider->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id', 'name_ar', 'name_en',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'),
                DB::raw('(3959 * acos(cos(radians(' . $latitude . ')) * cos(radians(latitude)) * cos(radians(longitude) - radians(' . $longitude . ')) + sin(radians(' . $latitude . ')) * sin(radians(latitude)))) AS distance'));

        } else {
            $provider = $provider->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id', 'name_ar', 'name_en',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'), DB::raw("'0' as distance"));

        }
        $provider = $provider->orderBy(DB::raw('RAND()'));

        return $provider->where('providers.status', 1)
            ->limit(PAGINATION_COUNT)
            ->get();

    }

    public function getProvidersBranch($userId = null, $longitude = null, $latitude = null, $order = "ASC", $rate = 0, $type_id = [], $nearest_date = 0, $specification_id = 0, $queryStr = "")
    {
        $provider = Provider::query();
        $provider = $provider->whereHas('doctors')->with(['type' => function ($q) use ($type_id) {
            $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
        }, 'doctors', 'favourites' => function ($qu) use ($userId) {
            $qu->where('user_id', $userId)->select('provider_id');
        }, 'city' => function ($q) {
            $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
        }, 'district' => function ($q) {
            $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
        }])->where('provider_id', '!=', null);
        //$provider = $provider->get();

        if (is_array($type_id) && count($type_id) > 0) {
            $provider = $provider->whereHas('provider', function ($q) use ($type_id) {
                $q->whereIn('type_id', $type_id);
            });
        }

        if ($queryStr != "") {
            $provider = $provider->whereHas('provider', function ($qu) use ($queryStr) {
                $qu->where('name_en', 'like', "%{$queryStr}%")->orwhere('name_ar', 'like', "%{$queryStr}%");
            });

        }

        if ($longitude != null && !empty($longitude) && $latitude != null && !empty($latitude)) {
            $provider = $provider->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id', 'name_ar', 'name_en',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'),
                DB::raw('(3959 * acos(cos(radians(' . $latitude . ')) * cos(radians(latitude)) * cos(radians(longitude) - radians(' . $longitude . ')) + sin(radians(' . $latitude . ')) * sin(radians(latitude)))) AS distance'));
            if ($rate == 1) {
                $provider = $provider->orderBy('rate', 'DESC')->orderBy('distance', $order);
            } else {
                $provider = $provider->orderBy('distance', $order);
            }
        } else {
            $provider = $provider->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id', 'name_ar', 'name_en',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'), DB::raw("'0' as distance"));

            if ($rate == 1) {
                $provider = $provider->orderBy('rate', 'DESC');
            }
        }
        return $provider->where('providers.status', 1)->paginate(10);
    }

    public function getSortedByDoctorDates($userId = null, $longitude = null, $latitude = null, $order = "ASC", $rate = 0, $type_id = [], $nearest_date = 0, $specification_id = 0)
    {

        $res = \App\Models\DoctorCalculation::with(['provider' => function ($q) use ($longitude, $latitude, $order, $rate) {
            if ($longitude != null && !empty($longitude) && $latitude != null && !empty($latitude)) {
                $q->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id',
                    DB::raw('name_' . $this->getCurrentLang() . ' as name'),
                    DB::raw('(3959 * acos(cos(radians(' . $latitude . ')) * cos(radians(latitude)) * cos(radians(longitude) - radians(' . $longitude . ')) + sin(radians(' . $latitude . ')) * sin(radians(latitude)))) AS distance'));
                if ($rate == 1) {
                    $q->orderBy('rate', 'DESC')->orderBy('distance', $order);
                } else {
                    $q->orderBy('distance', $order);
                }
            } else {
                $q->select('id', 'rate', 'logo', 'longitude', 'latitude', 'type_id', 'street', 'address', 'city_id', 'district_id', 'provider_id',
                    DB::raw('name_' . $this->getCurrentLang() . ' as name'), DB::raw("'0' as distance"));
                if ($rate == 1) {
                    $q->orderBy('rate', 'DESC');
                }
            }
        },
            'provider.type' => function ($q) use ($type_id) {
                $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
            }, 'provider.favourites' => function ($qu) use ($userId) {
                $qu->where('user_id', $userId)->select('provider_id');
            }, 'provider.city' => function ($q) {
                $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
            }, 'provider.district' => function ($q) {
                $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
            }])
            ->whereHas('provider', function ($q) use ($type_id) {   /*if has branches*/
                $q->where('provider_id', '!=', null);
                if (is_array($type_id) && count($type_id) > 0) {
                    $q->whereHas('provider', function ($q) use ($type_id) {
                        $q->whereIn('type_id', $type_id);
                    });
                }
            })
            // ->where('providers.status', 1)
            ->select('id', 'name_ar', 'name_en', 'provider_id')->where('specification_id', $specification_id)->paginate(10);
        $providers = [];
        // foreach($res->data as $data){
        //   $providers =  $data->provider;
        //}
        //$providers = collect($providers);

        $sorted = $res->sortBy(function ($a) {
            return strtotime($a->available_time);
        })->values()->all();
        $matched = [];
        foreach ($sorted as $sort) {
            if (!in_array($sort->provider->id, $matched)) {
                $sort->provider->favourite = count($sort->provider->favourites) > 0 ? 1 : 0;
                $sort->provider->distance = (string)number_format($sort->provider->distance * 1.609344, 2);
                unset($sort->provider->favourites);
                if ($sort->provider && !empty($sort->provider)) {
                    $providers[] = $sort->provider;
                    $matched[] = $sort->provider->id;
                }
            }
        }
        return [$res, $providers, count($matched)];
    }

    public function getAllProviderTypes()
    {
        return ProviderType::select('id', DB::raw('name_' . app()->getLocale() . ' as name'))->get();
    }

    public function checkProviderTypeByID($id)
    {
        $providerType = ProviderType::find($id);
        if ($providerType != null)
            return true;
        return false;
    }

    public function getReservationByNo($no, $provider_id)
    {

        $provider = Provider::where('id', $provider_id)->first();
        if ($provider->provider_id == null) { // main provider
            $branchesIds = $provider->providers()->pluck('id')->toArray();  // branches ids
        } else {  //branch
            $branchesIds = [$provider->id];
        }
        return Reservation::where(function ($q) use ($no, $provider_id, $branchesIds) {
            $q->where('reservation_no', $no)->where(function ($qq) use ($provider_id, $branchesIds) {
                $qq->where('provider_id', $provider_id)->orWhere(function ($qqq) use ($branchesIds) {
                    $qqq->whereIN('provider_id', $branchesIds);
                });
            });
        })->with(['user', 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }])->first();

    }

    public function checkIfThereReservationsNeedToClosed($no, $provider_id, $list = true)
    {
        $need_To_finish = 0;
        $provider = Provider::where('id', $provider_id)->first();
        if ($provider->provider_id == null) { // main provider
            $branchesIds = $provider->providers()->pluck('id')->toArray();  // branches ids
        } else {  //branch
            $branchesIds = [$provider->id];
        }

        //doctor and offers reservations
        $reservations = Reservation::where(function ($q) use ($no, $provider_id, $branchesIds) {
            $q->where(function ($qq) use ($provider_id, $branchesIds) {
                $qq->where('provider_id', $provider_id)->orWhere(function ($qqq) use ($branchesIds) {
                    $qqq->whereIN('provider_id', $branchesIds);
                });
            });
        })->where('approved', 1)
            ->whereDate('day_date', '<=', date('Y-m-d'))
            ->get();

        //services reservations
        $services_reservations = ServiceReservation::where(function ($q) use ($no, $provider_id, $branchesIds) {
            $q->where(function ($qq) use ($provider_id, $branchesIds) {
                $qq->where('branch_id', $provider_id)->orWhere(function ($qqq) use ($branchesIds) {
                    $qqq->whereIN('branch_id', $branchesIds);
                });
            });
        })->where('approved', 1)
            ->whereDate('day_date', '<=', date('Y-m-d'))
            ->get();

        if (isset($reservations) && $reservations->count() > 0) {
            foreach ($reservations as $reservation) {
                $day_date = $reservation->day_date . ' ' . $reservation->from_time;
                $reservation_date = date('Y-m-d H:i:s', strtotime($day_date));
                $currentDate = date('Y-m-d H:i:s');
                $fdate = $reservation_date;
                $tdate = $currentDate;
                $datetime1 = new DateTime($fdate);
                $datetime2 = new DateTime($tdate);
                $interval = $datetime1->diff($datetime2);
                $hours = $interval->format('%a');
                if ($hours >= 1) {
                    $need_To_finish++;
                }
            }
        }


        if (isset($services_reservations) && $services_reservations->count() > 0) {
            foreach ($services_reservations as $reservation) {
                $day_date = $reservation->day_date . ' ' . $reservation->from_time;
                $reservation_date = date('Y-m-d H:i:s', strtotime($day_date));
                $currentDate = date('Y-m-d H:i:s');
                $fdate = $reservation_date;
                $tdate = $currentDate;
                $datetime1 = new DateTime($fdate);
                $datetime2 = new DateTime($tdate);
                $interval = $datetime1->diff($datetime2);
                $hours = $interval->format('%a');
                if ($hours >= 1) {
                    $need_To_finish++;
                }
            }
        }

        return $need_To_finish;
    }

    public function NewReservations($providers)
    {
        return Reservation::with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }
            , 'paymentMethod' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'user' => function ($q) {
                $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                    ->with(['insuranceCompany' => function ($qu) {
                        $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])
            ->whereIn('provider_id', $providers)
            ->where('approved', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    public function NewReservationsByType($providers, $type)
    {
        if ($type == 'home_services') {
            return $this->getHomeServicesNewReservations($providers);
        } elseif ($type == 'clinic_services') {
            return $this->getClinicServicesNewReservations($providers);
        } elseif ($type == 'doctor') {
            return $this->getDoctorNewReservations($providers);
        } elseif ($type == 'offer') {
            return $this->getOfferNewReservations($providers);
        } else {
            // return all reservations

            return $this->getAllNewReservations($providers);
        }
    }

    public function currentReservationsByType($providers, $type)
    {
        if ($type == 'home_services') {
            return $this->getHomeServicesCurrentReservations($providers);
        } elseif ($type == 'clinic_services') {
            return $this->getClinicServicesCurrentReservations($providers);
        } elseif ($type == 'doctor') {
            return $this->getDoctorCurrentReservations($providers);
        } elseif ($type == 'offer') {
            return $this->getOfferCurrentReservations($providers);
        } else {
            // return all reservations
            return $this->getAllCurrentReservations($providers);
        }
    }

    protected function getHomeServicesNewReservations($providers)
    {
        return $reservations = ServiceReservation::whereHas('type', function ($e) {
            $e->where('id', 1);
        })->with(['service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type', 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }
        ])
            ->whereIn('branch_id', $providers)
            ->where('approved', 0)
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getClinicServicesNewReservations($providers)
    {
        return $reservations = ServiceReservation::whereHas('type', function ($e) {
            $e->where('id', 2);
        })->with(['service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type', 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }
        ])
            ->whereIn('branch_id', $providers)
            ->where('approved', 0)
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getDoctorNewReservations($providers)
    {

        return $reservations = Reservation::with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }
            , 'paymentMethod' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'user' => function ($q) {
                $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                    ->with(['insuranceCompany' => function ($qu) {
                        $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])
            ->whereIn('provider_id', $providers)
            ->where('approved', 0)
            ->whereNotNull('doctor_id')
            ->where('doctor_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getOfferNewReservations($providers)
    {
        return $reservations = Reservation::with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title'),
                'expired_at',
                'price',
                'price_after_discount'
            );
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }])
            ->whereIn('provider_id', $providers)
            ->where('approved', 0)
            ->whereNotNull('offer_id')
            ->where('offer_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    public function getAllNewReservations($providers)
    {
        $doctor_reservations = Reservation::doctorSelection()
            ->whereIn('provider_id', $providers)
            ->where('approved', 0)
            ->whereNotNull('doctor_id')
            ->where('doctor_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC');


        $home_services_reservations = ServiceReservation::serviceSelection()
            ->serviceSelection()
            ->whereHas('type', function ($e) {
                $e->where('id', 1);
            })
            ->whereIn('branch_id', $providers)
            ->where('approved', 0)
            ->orderBy('id', 'DESC');

        $clinic_services_reservations = ServiceReservation::serviceSelection()->whereHas('type', function ($e) {
            $e->where('id', 2);
        })
            ->whereIn('branch_id', $providers)
            ->where('approved', 0)
            ->orderBy('id', 'DESC');

        return Reservation::OfferReservationSelection()->with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title'),
                'expired_at',
                'price',
                'price_after_discount'
            );
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }])
            ->whereIn('provider_id', $providers)
            ->where('approved', 0)
            ->whereNotNull('offer_id')
            ->where('offer_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->union($doctor_reservations)
            ->union($home_services_reservations)
            ->union($clinic_services_reservations)
            ->paginate(PAGINATION_COUNT);
    }

    public function getAllCurrentReservations($providers)
    {
        $doctor_reservations = Reservation::doctorSelection()
            ->whereIn('provider_id', $providers)
            ->where('approved', 1)
            ->whereNotNull('doctor_id')
            ->where('doctor_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC');


        $home_services_reservations = ServiceReservation::serviceSelection()
            ->serviceSelection()
            ->whereHas('type', function ($e) {
                $e->where('id', 1);
            })
            ->whereIn('branch_id', $providers)
            ->where('approved', 1)
            ->orderBy('id', 'DESC');

        $clinic_services_reservations = ServiceReservation::serviceSelection()->serviceSelection()->whereHas('type', function ($e) {
            $e->where('id', 2);
        })
            ->whereIn('branch_id', $providers)
            ->where('approved', 1)
            ->orderBy('id', 'DESC');

        return Reservation::OfferReservationSelection()->with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title'),
                'expired_at',
                'price',
                'price_after_discount'
            );
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }])
            ->whereIn('provider_id', $providers)
            ->where('approved', 1)
            ->whereNotNull('offer_id')
            ->where('offer_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->union($doctor_reservations)
            ->union($home_services_reservations)
            ->union($clinic_services_reservations)
            ->paginate(PAGINATION_COUNT);
    }

    protected function getHomeServicesCurrentReservations($providers)
    {

        return $reservations = ServiceReservation::whereHas('type', function ($e) {
            $e->where('id', 1);
        })->with(['service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type', 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }
        ])
            ->whereIn('branch_id', $providers)
            ->where('approved', 1)
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getClinicServicesCurrentReservations($providers)
    {
        return $reservations = ServiceReservation::whereHas('type', function ($e) {
            $e->where('id', 2);
        })->with(['service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type', 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }
        ])
            ->whereIn('branch_id', $providers)
            ->where('approved', 1)
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getDoctorCurrentReservations($providers)
    {

        return Reservation::with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        },
            'paymentMethod' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'coupon' => function ($qu) {
                $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price', 'price_after_discount');
            }
            , 'user' => function ($q) {
                $q->select('id', 'name', 'insurance_image', 'insurance_company_id', 'mobile')
                    ->with(['insuranceCompany' => function ($qu) {
                        $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])->whereIn('provider_id', $providers)
            ->whereIn('approved', [1])
            ->whereNotNull('doctor_id')
            ->where('doctor_id', '!=', 0)
            /* ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('day_date')
            ->orderBy('from_time')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getOfferCurrentReservations($providers)
    {
        return $reservations = Reservation::with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title'),
                'expired_at',
                'price',
                'price_after_discount'
            );
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }])
            ->whereIn('provider_id', $providers)
            ->where('approved', 1)
            ->whereNotNull('offer_id')
            ->where('offer_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }


    public function recordReservationsByType($providers, $type)
    {
        if ($type == 'home_services') {
            return $this->getHomeServicesRecordReservations($providers);
        } elseif ($type == 'clinic_services') {
            return $this->getClinicServicesRecordReservations($providers);
        } elseif ($type == 'doctor') {
            return $this->getDoctorRecordReservations($providers);
        } elseif ($type == 'offer') {
            return $this->getOfferRecordReservations($providers);
        } else {
            // return all reservations

            return $this->getAllRecordReservations($providers);
        }
    }

    protected function getHomeServicesRecordReservations($providers)
    {
        return $reservations = ServiceReservation::whereHas('type', function ($e) {
            $e->where('id', 1);
        })->with(['service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type', 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }
        ])
            ->whereIn('branch_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getClinicServicesRecordReservations($providers)
    {
        return $reservations = ServiceReservation::whereHas('type', function ($e) {
            $e->where('id', 2);
        })->with(['service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type', 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }
        ])
            ->whereIn('branch_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getDoctorRecordReservations($providers)
    {

        return $reservations = Reservation::with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }
            , 'paymentMethod' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'user' => function ($q) {
                $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                    ->with(['insuranceCompany' => function ($qu) {
                        $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])
            ->whereIn('provider_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->whereNotNull('doctor_id')
            ->where('doctor_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    protected function getOfferRecordReservations($providers)
    {
        return $reservations = Reservation::with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title'),
                'expired_at',
                'price'
            );
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }])
            ->whereIn('provider_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->whereNotNull('offer_id')
            ->where('offer_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->paginate(PAGINATION_COUNT);
    }

    public function getAllRecordReservations($providers)
    {
        $doctor_reservations = Reservation::doctorSelection()
            ->whereIn('provider_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->whereNotNull('doctor_id')
            ->where('doctor_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC');


        $home_services_reservations = ServiceReservation::serviceSelection()
            ->serviceSelection()
            ->whereHas('type', function ($e) {
                $e->where('id', 1);
            })
            ->whereIn('branch_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->orderBy('id', 'DESC');

        $clinic_services_reservations = ServiceReservation::serviceSelection()->serviceSelection()->whereHas('type', function ($e) {
            $e->where('id', 2);
        })
            ->whereIn('branch_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->orderBy('id', 'DESC');

        return Reservation::OfferReservationSelection()->with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title'),
                'expired_at',
                'price'
            );
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'email', 'address', 'insurance_image', 'insurance_company_id', 'mobile')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }, 'provider' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'service' => function ($g) {
            $g->select('id', 'specification_id', \Illuminate\Support\Facades\DB::raw('title_' . app()->getLocale() . ' as title'), 'price', 'clinic_price', 'home_price')
                ->with(['specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'type' => function ($qq) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }])
            ->whereIn('provider_id', $providers)
            ->whereIn('approved', [2, 3, 5])   //reservations which cancelled by user or branch or complete
            ->whereNotNull('offer_id')
            ->where('offer_id', '!=', 0)
            /*  ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('id', 'DESC')
            ->union($doctor_reservations)
            ->union($home_services_reservations)
            ->union($clinic_services_reservations)
            ->paginate(PAGINATION_COUNT);
    }

    public function AcceptedReservations($providers = [])
    {
        return Reservation::with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        },
            'paymentMethod' => function ($qu) {
                $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'coupon' => function ($qu) {
                $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price', 'price_after_discount');
            }
            , 'user' => function ($q) {
                $q->select('id', 'name', 'insurance_image', 'insurance_company_id', 'mobile')
                    ->with(['insuranceCompany' => function ($qu) {
                        $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])->whereIn('provider_id', $providers)
            ->whereIn('approved', [1])
            /* ->whereDate('day_date', '>=', Carbon::now()->format('Y-m-d'))*/
            ->orderBy('day_date')
            ->orderBy('from_time')
            ->paginate(PAGINATION_COUNT);
    }

    public function getReservationByNoWihRelation($no, $provider_id)
    {
        $provider = Provider::where('id', $provider_id)->first();
        if ($provider->provider_id == null) { // main provider
            $branchesIds = $provider->providers()->pluck('id')->toArray();  // branches ids
        } else {  //branch
            $branchesIds = [$provider->id];
        }

        return Reservation::with(['commentReport' => function ($q) use ($provider_id) {
            $q->where('provider_id', $provider_id);
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', 'nationality_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        },
            'coupon' => function ($qu) {
                $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
            }
            , 'user' => function ($q) {
                $q->select('id', 'name', 'mobile', 'insurance_company_id', 'insurance_image', 'mobile')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }, 'provider' => function ($qq) {
                $qq->whereNotNull('provider_id')->select('id', DB::raw('name_' . app()->getLocale() . ' as name'), 'latitude', 'longitude')
                    ->with(['provider' => function ($g) {
                        $g->select('id', 'type_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                            ->with(['type' => function ($gu) {
                                $gu->select('id', 'type_id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])->


        where(function ($q) use ($no, $provider_id, $branchesIds) {
            $q->where('reservation_no', $no)->where(function ($qq) use ($provider_id, $branchesIds) {
                $qq->where('provider_id', $provider_id)->orWhere(function ($qqq) use ($branchesIds) {
                    $qqq->whereIN('provider_id', $branchesIds);
                });
            });
        })->first();
    }

    public
    function getReservationByNoWihRelationFront($no, $provider_id)
    {
        return Reservation::with(['commentReport' => function ($q) use ($provider_id) {
            $q->where('provider_id', $provider_id);
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', 'nationality_id', 'photo', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        },
            'coupon' => function ($qu) {
                $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
            }
            , 'user' => function ($q) {
                $q->select('id', 'name', 'mobile', 'insurance_company_id', 'insurance_image', 'mobile')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }, 'provider' => function ($qq) {
                $qq->whereNotNull('provider_id')->select('id', DB::raw('name_' . app()->getLocale() . ' as name', 'application_percentage_bill'))
                    ->with(['provider' => function ($g) {
                        $g->select('id', 'type_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                            ->with(['type' => function ($gu) {
                                $gu->select('id', 'type_id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                    }]);
            }, 'people' => function ($p) {
                $p->select('id', 'name', 'insurance_company_id', 'insurance_image', 'phone')->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }])->where('reservation_no', $no)->where('provider_id', $provider_id)->first();
    }


    public
    function getDoctorsInBranch($id)
    {
        return Doctor::with(['provider' => function ($q) {
            $q->select('id', 'branch_no', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'specification', 'nationality', 'nickname', 'times'
            , 'insuranceCompanies' => function ($q2) {
                $q2->select('insurance_companies.id', 'name_ar', 'name_en');
            }
        ])->where('provider_id', $id)
            // ->where('doctors.status',1)
            ->select('*',
                DB::raw('name_' . app()->getLocale() . ' as name'), 'gender', 'nickname_id', 'specification_id', 'nationality_id',
                DB::raw('abbreviation_' . $this->getCurrentLang() . ' as abbreviation')
            )
            ->paginate(10);
    }

    public
    function getProviderReservations($provider_id, $branches = [], $branchId = [], $fromDate = null, $toDate = null,
                                     $paymentMethodId = null, $doctorId = null, $countfalg = false, $status = null)
    {
        $reservations = Reservation::query();
        $prices = 0;

        $conditions = [];
        if ($status != null && in_array($status, [2, 3])) {
            array_push($conditions, $status);
        }

        $reservations = $reservations->with(['commentReport' => function ($q) use ($provider_id) {
            $q->where('provider_id', $provider_id);
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', 'nationality_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) use ($provider_id, $branches, $branchId) {
            $qq->where('id', $provider_id)
                ->orWhereIn('id', (count($branchId) > 0 ? $branchId : $branches))
                ->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }])->where('provider_id', $provider_id);


        if (!empty($branchId) && count($branchId)) {
            foreach ($branchId as $id) {
                if ($id != null && $id != 0)
                    $reservations = $reservations->where('provider_id', $id);
            }
        }
        if ($doctorId != null && $doctorId != 0)
            $reservations = $reservations->where('doctor_id', $doctorId);

        if ($paymentMethodId != null && $paymentMethodId != 0)
            $reservations = $reservations->where('payment_method_id', $paymentMethodId);

        if ($fromDate != null && !empty($fromDate))
            $reservations = $reservations->where('day_date', '>=', date('Y-m-d', strtotime($fromDate)));

        if ($toDate != null && !empty($toDate))
            $reservations = $reservations->where('day_date', '<=', date('Y-m-d', strtotime($toDate)));

        if ($countfalg)
            return $reservations->whereIn('approved', [3])->count();   // only completed

        if (!empty($conditions)) {
            $result = $reservations->whereIn('approved', $conditions)->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        } else {
            $result = $reservations->whereIn('approved', [2, 3])->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        }

        if ($status == 3 || $status == 0) { //filter only get complete reservation
            $count = $reservations->whereIn('approved', [3])->count();    // only completed
        } else { // not count anything

            $count = 0;
        }

        // $sum = $reservations->whereIn('approved', [3])->sum('price'); // only completed

        if (isset($result) && $result->count() > 0) {
            foreach ($result as $res) {
                $prices += $res->admin_value_from_reservation_price_Tax;
            }
        }

        return ['count' => $count,
            'prices' => $prices,
            'reservations' => $result];
    }

    public
    function getMainProviderReservations($provider_id, $branches = [], $branchId = 0, $fromDate = null, $toDate = null,
                                         $paymentMethodId = null, $doctorId = null, $countfalg = false, $status = null)
    {
        $prices = 0;
        $conditions = [];
        if ($status != null && in_array($status, [2, 3])) {
            array_push($conditions, $status);
        }

        $reservations = Reservation::query();
        $reservations = $reservations->with(['commentReport' => function ($q) use ($provider_id) {
            $q->where('provider_id', $provider_id);
        }, 'doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', 'nationality_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) use ($provider_id, $branches, $branchId) {
            $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }])->whereIn('provider_id', $branches);

        if (!empty($branchId) && $branchId != 0)
            $reservations = $reservations->where('provider_id', $branchId);

        if ($doctorId != null && $doctorId != 0)
            $reservations = $reservations->where('doctor_id', $doctorId);

        if ($paymentMethodId != null && $paymentMethodId != 0)
            $reservations = $reservations->where('payment_method_id', $paymentMethodId);

        if ($fromDate != null && !empty($fromDate))
            $reservations = $reservations->where('day_date', '>=', date('Y-m-d', strtotime($fromDate)));

        if ($toDate != null && !empty($toDate))
            $reservations = $reservations->where('day_date', '<=', date('Y-m-d', strtotime($toDate)));

        if ($countfalg)
            return $reservations->whereIn('approved', [3])->count();   // only completed


        if (!empty($conditions)) {
            $result = $reservations->whereIn('approved', $conditions)->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        } else {
            $result = $reservations->whereIn('approved', [2, 3])->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        }

        /* if ($status == 3 || $status == 0) { //filter only get complete reservation
             $count = $reservations->whereIn('approved', [3])->count();    // only completed
         } else { // not count anything

             $count = 0;
         }*/

        /* if (isset($result) && $result->count() > 0) {
             foreach ($result as $res) {
                 $prices += $res->admin_value_from_reservation_price_Tax;
             }
         }*/
        return [
            //'count' => $count,
            // 'prices' => $prices,
            'reservations' => $result];
    }


    //deprecated
    public
    function getBranchReservations($provider_id, $branches = [], $branchId = [], $fromDate = null, $toDate = null,
                                   $paymentMethodId = null, $doctorId = null, $countfalg = false, $status = null)
    {
        $reservations = Reservation::query();
        $prices = 0;
        $conditions = [];
        if ($status != null && in_array($status, [2, 3])) {
            array_push($conditions, $status);
        }

        $reservations = $reservations->with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', 'nationality_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) use ($provider_id, $branches, $branchId) {
            $qq->where('id', $provider_id)
                ->orWhereIn('id', (count($branchId) > 0 ? $branchId : $branches))
                ->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }])->whereHas('provider', function ($q) use ($provider_id) {
            $q->where('provider_id', '=', $provider_id);
        });

        if (!empty($branchId) && count($branchId))
            foreach ($branchId as $id) {
                if ($id != null && $id != 0)
                    $reservations = $reservations->where('provider_id', $id);
            }


        if ($doctorId != null && $doctorId != 0)
            $reservations = $reservations->where('doctor_id', $doctorId);

        if ($paymentMethodId != null && $paymentMethodId != 0)
            $reservations = $reservations->where('payment_method_id', $paymentMethodId);

        if ($fromDate != null && !empty($fromDate))
            $reservations = $reservations->where('day_date', '>=', date('Y-m-d', strtotime($fromDate)));

        if ($toDate != null && !empty($toDate))
            $reservations = $reservations->where('day_date', '<=', date('Y-m-d', strtotime($toDate)));

        if ($countfalg)
            return $reservations->whereIn('approved', [3])->count();   // only completed

        if (!empty($conditions)) {
            $result = $reservations->whereIn('approved', $conditions)->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        } else {
            $result = $reservations->whereIn('approved', [2, 3])->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        }

        /*
                if ($status == 3 || $status == 0) { //filter only get complete reservation
                    $count = $reservations->whereIn('approved', [3])->count();    // only completed
                } else { // not count anything

                    $count = 0;
                }*/

        //$sum = $reservations->whereIn('approved', [3])->sum('price'); // only completed

        /* if (isset($result) && $result->count() > 0) {
             foreach ($result as $res) {
                 $prices += $res->admin_value_from_reservation_price_Tax;
             }
         }*/
        return [
            //'count' => $count,
            // 'prices' => $prices,
            'reservations' => $result];
    }

    public
    function getFixedReservations($provider_id, $doctorId = null, $fromDate = null, $toDate = null, $countfalg = false, $status = null)
    {
        $reservations = Reservation::query();
        $prices = 0;
        $conditions = [];
        if ($status != null && in_array($status, [2, 3])) {
            array_push($conditions, $status);
        }
        $reservations = $reservations->with(['doctor' => function ($g) {
            $g->select('id', 'nickname_id', 'specification_id', 'nationality_id', DB::raw('name_' . app()->getLocale() . ' as name'))
                ->with(['nickname' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'specification' => function ($g) {
                    $g->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'coupon' => function ($qu) {
            $qu->select('id', 'coupons_type_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'rejectionResoan' => function ($rs) {
            $rs->select('id', DB::raw('name_' . app()->getLocale() . ' as rejection_reason'));
        }, 'user' => function ($q) {
            $q->select('id', 'name', 'mobile', 'insurance_image', 'insurance_company_id')
                ->with(['insuranceCompany' => function ($qu) {
                    $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
        }, 'provider' => function ($qq) use ($provider_id) {
            $qq->where('id', $provider_id)
                //  ->orWhereIn('id', (count($branchId) > 0 ? $branchId : $branches))
                ->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'people' => function ($p) {
            $p->select('id', 'name', 'insurance_company_id', 'insurance_image')->with(['insuranceCompany' => function ($qu) {
                $qu->select('id', 'image', DB::raw('name_' . app()->getLocale() . ' as name'));
            }]);
        }])->where('provider_id', $provider_id)->whereNotNull('user_id');

        if ($doctorId != null && $doctorId != 0)
            $reservations = $reservations->where('doctor_id', $doctorId);

        if ($fromDate != null && !empty($fromDate))
            $reservations = $reservations->where('day_date', '>=', date('Y-m-d', strtotime($fromDate)));

        if ($toDate != null && !empty($toDate))
            $reservations = $reservations->where('day_date', '<=', date('Y-m-d', strtotime($toDate)));

        //return $result = $reservations->whereIn('approved',[2,3]) ->orderBy('day_date')->orderBy('from_time')->paginate(15);   // refuse and completed

        if ($countfalg) {
            return $reservations->whereIn('approved', [3])->count();   // only completed
        }


        //$sum = $reservations->where('approved', 3)->sum('price'); // only completed
        if (!empty($conditions)) {
            $result = $reservations->whereIn('approved', $conditions)->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        } else {
            $result = $reservations->whereIn('approved', [2, 3])->orderBy('day_date')->orderBy('from_time')->paginate(10);   // refuse and completed
        }

        /*   if ($status == 3 || $status == 0) { //filter only get complete reservation
               $count = $reservations->whereIn('approved', [3])->count();    // only completed
           } else { // not count anything

               $count = 0;
           }*/
        //sum for each page
        /* if (isset($result) && $result->count() > 0) {
             foreach ($result as $res) {
                 $prices += $res->admin_value_from_reservation_price_Tax;
             }
         }*/

        return [
            //'count' => $count,
            //  'prices' => $prices,
            'reservations' => $result];
    }

    public
    function getReservationByID($id)
    {
        return Reservation::find($id);
    }

    public
    function getProviderMessages($id, $type = null)
    {
        $conditions = [];
        if ($type != null) {
            array_push($conditions, ['type', '=', $type]);
        }
        if (!empty($conditions) && count($conditions) > 0) {

            return Ticket::where('actor_id', $id)->where('actor_type', 1)->where($conditions)->orderBy('id', 'DESC')->paginate(10);
        }

        return Ticket::where('actor_id', $id)->where('actor_type', 1)->orderBy('id', 'DESC')->paginate(10);
    }


    public
    function getUserMessages($id, $type = null)
    {
        $conditions = [];
        if ($type != null) {
            array_push($conditions, ['type', '=', $type]);
        }

        if (!empty($conditions) && count($conditions) > 0) {
            return Ticket::where('actor_id', $id)->where('actor_type', 2)->where($conditions)->orderBy('id', 'DESC')->paginate(10);
        }

        return Ticket::where('actor_id', $id)->where('actor_type', 2)->orderBy('id', 'DESC')->paginate(10);
    }

    public
    function getMessageByID($id, $providerId = null)
    {
        $message = Message::query();
        if ($providerId != null && $providerId != 0)
            $message = $message->where('provider_id', $providerId)->whereNull('message_id');

        return $message->where('id', $id)->first();
    }

    public
    function getLastMessageForProvider($id)
    {
        return Message::where('provider_id', $id)->whereNull('message_id')->orderBy('order', 'DESC')->first();
    }

    public
    function getBranches($provider)
    {
        return $provider->providers()->with(['type' => function ($q) {
            $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'city' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'district' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }])->paginate(10);
    }

    public
    function checkBranchNo($mainProvider_id, $branch_id, $branch_no)
    {
        return Provider::where('provider_id', $mainProvider_id)->where('id', '!=', $branch_id)->where('branch_no', $branch_no)->first();
    }

    public
    function checkProviderMessageById($id, $msg_id)
    {
        return Message::where('provider_id', $id)->where('id', $msg_id)->first();
    }

    public
    function getMessageReplies($id)
    {
        return Message::with(['provider' => function ($q) {
            $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'manager' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }])->where('message_id', $id)
            ->select('id', 'message', 'manager_id', 'provider_id', 'message_no', 'order')
            ->orderBy('order')->paginate(10);
    }

    public
    function getProviderBalance($IDs)
    {
        return Provider::select('id', 'city_id', 'district_id', DB::raw('name_' . app()->getLocale() . ' as name'), 'balance', 'address', 'street')
            ->with(['city' => function ($q) {
                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }, 'district' => function ($qq) {
                $qq->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
            }])
            ->whereIn('id', $IDs)
            ->paginate(10);
    }

    public
    function sumBalance($IDs)
    {
        $balance = Provider::whereIn('id', $IDs)->sum('balance');
        $balanceWithTax = $balance;
        return $balanceWithTax;
    }


}
