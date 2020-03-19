<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Filter;
use App\Models\Reservation;
use App\Models\User;
use App\Traits\Dashboard\PublicTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Dashboard\OfferTrait;
use App\Models\Provider;
//use App\Models\Doctor;
use App\Models\Offer;
use App\Models\OfferBranch;
use Validator;
use Flashy;
use DB;

class OfferController extends Controller
{
    use OfferTrait, PublicTrait;

    public function getDataTable()
    {
        return $this->getAll();
    }

    public function index()
    {
        return view('offers.index');
    }

    public function mostReserved()
    {
        $reservations = Reservation::with(['coupon' => function ($qu) {
            $qu->select('id', 'provider_id', DB::raw('title_' . app()->getLocale() . ' as title'), 'code', 'photo', 'price', 'price_after_discount');
            $qu->with(['provider' => function ($q) {
                $q->select('id', 'name_ar');
            }]);
        }])
            ->whereNotNull('promocode_id')->groupBy('promocode_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get(['promocode_id', DB::raw('count(promocode_id) as count')]);

        return view('offers.mostreserved', compact('reservations'));
    }

    public function getDataTableOfferBranches($offerId)
    {
        try {
            return $this->getBranchTable($offerId);
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    /*public function getDataTablePromoCodeDoctors($promoId)
    {
        try {
            return $this->getDoctorTable($promoId);
        } catch (\Exception $ex) {
            return view('errors.404');
        }
    }*/

    public function add()
    {
        $data['providers'] = $this->getAllMainActiveProviders();
        // $specifications = $this->getAllSpecifications();
        $data['categories'] = $this->getAllCategoriesCollection();    // categories
        $data['users'] = $this->getAllActiveUsers();
        $data['featured'] = collect(['1' => 'غير مميز', '2' => 'مميز']);
        return view('offers.add', $data);
    }

    public function getProviderBranches(Request $request)
    {
        $parent_id = 0;
        if ($request->parent_id) {
            $parent_id = $request->parent_id;
        }
        /* $couponBranches =[];
         if(isset($request -> couponId)){
             $couponBranches = PromoCode_branch::where('promocodes_id',$request -> couponId) -> pulck('branch_id') -> toArray();
         }*/

        if (isset($request->couponId) && $request->couponId != null)
            $branches = Provider::where('provider_id', $parent_id)->select('name_ar', 'id', 'provider_id', DB::raw('IF ((SELECT count(id) FROM offers_branches WHERE offers_branches.promocodes_id = ' . $request->couponId . ' AND providers.id = offers_branches.branch_id) > 0, 1, 0) as selected'))->get();
        else
            $branches = Provider::where('provider_id', $parent_id)->select('name_ar', 'id', 'provider_id', DB::raw('0 as selected'))->get();

        $view = view('includes.loadbranches', compact('branches'))->renderSections();
        return response()->json([
            'content' => $view['main'],
        ]);
    }


    /*public function getBranchDoctors(Request $request)
    {
        $parent_id = [];
        if ($request->branche_id && count($request->branche_id) > 0) {
            $parent_id = $request->branche_id;
        }
        if (isset($request->couponId) && $request->couponId != null)
            $doctors = Doctor::whereIn('provider_id', $parent_id)->select('name_ar', 'id', 'provider_id', DB::raw('IF ((SELECT count(id) FROM promocodes_doctors WHERE promocodes_doctors.promocodes_id = ' . $request->couponId . ' AND doctors.id = promocodes_doctors.doctor_id) > 0, 1, 0) as selected'))->get();
        else
            $doctors = Doctor::whereIn('provider_id', $parent_id)->select('name_ar', 'id', 'provider_id', DB::raw('0 as selected'))->get();

        $view = view('includes.loaddoctors', compact('doctors'))->renderSections();
        return response()->json([
            'content' => $view['main'],
        ]);
    }*/

    public function branches($offerId)
    {
        return view('offers.branches')->with('offerId', $offerId);
    }

    /*public function doctors($promoCodeId)
    {

         return  promoCode::where('id',$promoCodeId)-> with(['PromoCodeDoctors' => function($q){
                $q -> select('*')
                   ->with(['doctor' => function($qq){
                     $qq -> select('id','name_ar') ;
                   }]);
          }]) -> get();

        return view('promoCode.doctors')->with('promoCodeId', $promoCodeId);

    }*/

    public function store(Request $request)
    {
        $rules = [
            "title_ar" => "required|max:255",
            "title_en" => "required|max:255",
            "available_count" => "sometimes|nullable|numeric",
            "expired_at" => "required|after_or_equal:" . date('Y-m-d'),
            "provider_id" => "required|exists:providers,id",
            "status" => "required|in:0,1",
            "photo" => "required|mimes:jpeg,bmp,jpg,png",
            "category_ids" => "required|array|min:1",
            "category_ids.*" => "required|exists:offers_categories,id",
            "featured" => "required|in:1,2",    // 1 -> not featured 2 -> featured
            "paid_coupon_percentage" => "sometimes|nullable|min:0",
            "discount" => "sometimes|nullable|min:0",
            "price" => "required|min:0",
            "price_after_discount" => "required|min:0",

            "available_count_type" => "required|in:once,more_than_once",
            "started_at" => "required|date",
            "gender" => "required|in:all,males,females",

        ];

        /*if ($request->coupons_type_id == 1) {
            $rules['discount'] = "required";
            $rules['code'] = "required|unique:promocodes,code|max:255";
        }

        if ($request->coupons_type_id == 2) {
            $rules['price'] = "required";
            $rules['paid_coupon_percentage'] = "required";
        }*/

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Flashy::error($validator->errors()->first());
            return redirect()->back()->withErrors($validator)->withInput($request->all());
        }
        $inputs = $request->only('code', 'discount', 'available_count', 'status', 'expired_at', 'provider_id', 'title_ar', 'title_en', 'price', 'application_percentage', 'featured', 'paid_coupon_percentage', 'price_after_discount');

        $fileName = "";
        if (isset($request->photo) && !empty($request->photo)) {
            $fileName = $this->uploadImage('copouns', $request->photo);
        }

        $inputs['photo'] = $fileName;
        $offer = $this->createOffer($inputs);

        $offer->categories()->attach($request->category_ids);

        if ($request->has('branchIds')) {
            $branchIds = array_filter($request->branchIds, function ($val) {
                return !empty($val);
            });
        }

        /*if ($request->has('doctorsIds')) {
            $doctorsIds = array_filter($request->doctorsIds, function ($val) {
                return !empty($val);
            });
        }*/

        if (!isset($request->users)) {
            if (!$request->filled('available_count')) {
                Flashy::error("لابد من ادخال العدد المتاح للعرض");
                return redirect()->back()->withErrors(['available_count' => 'لابد من ادخال العدد المتاح للعرض'])->withInput($request->all());
            }
        }
        DB::beginTransaction();
        $users = [];  // allowed users to use this offer
        if ($request->has('users') && is_array($request->users)) {
            $usersIds = array_filter($request->users, function ($val) {
                return !empty($val);
            });
            //check if all ids exists in user table
            $count = count($usersIds);
            $usersIdCount = User::whereIn('id', $usersIds)->count('id');
            if ($count != $usersIdCount) {
                Flashy::error("بعض من المستخدمين غير موجودين لدينا");
                return redirect()->back()->withErrors(['users' => 'بعض من المستخدمين غير موجودين لدينا'])->withInput($request->all());
            }

            $users = $usersIds;
        }

        if ($offer->id) {
            if ($request->has('branchIds')) {
                $this->saveCouponBranchs($offer->id, $branchIds, $offer->provider_id);
            }

            /*if ($request->has('doctorsIds')) {
                // save doctors for  only previous branches
                $this->saveCouponDoctors($offer->id, $doctorsIds, $offer->provider_id);
            }*/

            $offer = Offer::find($offer->id);
            //allowed users to use this offer
            if (!empty($users) && count($users) > 0) {
                $offer->users()->attach($request->users);
                $offer->update(['general' => 0]);
            } else {
                //all user can see offer
                //$offer->users()->attach(User::active() -> pluck('id') -> toArray());
                $offer->update(['general' => 1]);
            }
        }

        DB::commit();

        Flashy::success('تم إضافة الكوبون بنجاح');
        return redirect()->route('admin.offers');
    }


    public function edit($id)
    {
        $data['offer'] = $this->getOfferByIdWithRelations($id);
        if ($data['offer'] == null)
            return view('errors.404');
        $data['providers'] = $this->getAllMainActiveProviders();
        $data['categories'] = $this->getAllCategoriesWithCurrentOfferSelected($data['offer']);
        $data['users'] = $this->getAllActiveUsersWithCurrentOfferSelected($data['offer']);
        $data['featured'] = collect(['1' => 'غير مميز', '2' => 'مميز']);
        return view('offers.edit', $data);
    }

    public function update($id, Request $request)
    {
        $offer = Offer::findOrFail($id);
        $rules = [
            "title_ar" => "required|max:255",
            "title_en" => "required|max:255",
            "available_count" => "sometimes|nullable|numeric",
            "expired_at" => "required|after_or_equal:" . date('Y-m-d'),
            "provider_id" => "required|exists:providers,id",
            "status" => "required|in:0,1",
            "photo" => "sometimes|nullable|mimes:jpeg,bmp,jpg,png",
            "category_ids" => "required|array|min:1",
            "category_ids.*" => "required|exists:offers_categories,id",
            "featured" => "required|in:1,2",    // 1 -> not featured 2 -> featured
            "paid_coupon_percentage" => "sometimes|nullable|min:0",
            "discount" => "sometimes|nullable|numeric|min:0",
            "price" => "required|min:0",
            "price_after_discount" => "required|min:0",

            "available_count_type" => "required|in:once,more_than_once",
            "started_at" => "required|date",
            "gender" => "required|in:all,males,females",

        ];

        /*if ($request->coupons_type_id == 1) {
            $rules['discount'] = "required";
            $rules['code'] = "required|max:255|unique:promocodes,code," . $id;
        }

        if ($request->coupons_type_id == 2) {
            //  $rules['price'] = "required";
            $rules['paid_coupon_percentage'] = "required";
        }*/

        $validator = Validator::make($request->all(), $rules
        );

        if ($validator->fails()) {
            Flashy::error($validator->errors()->first());
            return redirect()->back()->withErrors($validator)->withInput($request->all());
        }
        $inputs = $request->only('code', 'discount', 'available_count', 'status', 'expired_at', 'provider_id', 'title_ar', 'title_en', 'price', 'price_after_discount', 'application_percentage', 'featured', 'paid_coupon_percentage');

        $fileName = $offer->photo;
        if (isset($request->photo) && !empty($request->photo)) {
            $fileName = $this->uploadImage('copouns', $request->photo);
        }
        DB::beginTransaction();

        $inputs['photo'] = $fileName;
        Offer::find($id)->update($inputs);

        if ($request->has('branchIds')) {
            $branchIds = array_filter($request->branchIds, function ($val) {
                return !empty($val);
            });
        }

        /*if ($request->has('doctorsIds')) {
            $doctorsIds = array_filter($request->doctorsIds, function ($val) {
                return !empty($val);
            });
        }*/

        if (!isset($request->users)) {
            if (!$request->filled('available_count')) {
                Flashy::error("لابد من ادخال العدد المتاح للعرض");
                return redirect()->back()->withErrors(['available_count' => 'لابد من ادخال العدد المتاح للعرض'])->withInput($request->all());
            }
        }

        $users = [];  // allowed users to use this offer
        if ($request->has('users') && is_array($request->users)) {
            $usersIds = array_filter($request->users, function ($val) {
                return !empty($val);
            });
            //check if all ids exists in user table
            $count = count($usersIds);
            $usersIdCount = User::whereIn('id', $usersIds)->count('id');
            if ($count != $usersIdCount) {
                Flashy::error("بعض من المستخدمين غير موجودين لدينا");
                return redirect()->back()->withErrors(['users' => 'بعض من المستخدمين غير موجودين لدينا'])->withInput($request->all());
            }

            $users = $usersIds;
        }


        if ($request->has('branchIds')) {
            OfferBranch::where('offer_id', $id)->delete();
            $this->saveCouponBranchs($id, $branchIds, $offer->provider_id);
        }

        /*if ($request->has('doctorsIds')) {
            // save doctors for  only previous branches
            PromoCode_Doctor::where('promocodes_id', $id)->delete();
            $this->saveCouponDoctors($id, $doctorsIds, $offer->provider_id);
        }*/

        //allowed users to use this offer
        if (!empty($users) && count($users) > 0) {
            $offer->users()->sync($request->users);
            $offer->update(['general' => 0]);
        } else {
            //all user can see offer
            // $promoCode->users()->sync(User::active()-> pluck('id') -> toArray());
            $offer->update(['general' => 1]);
        }
        $offer->categories()->sync($request->category_ids);

        DB::commit();
        Flashy::success('تم تحديث الكوبون بنجاح');
        return redirect()->route('admin.offers');
    }

    public function destroy($id)
    {

        $offer = $this->getOfferById($id);
        if ($offer == null)
            return view('errors.404');

        if (count($offer->reservations) == 0) {
            $offer->deleteWithRelations();
            Flashy::success('تم مسح العرض بنجاح');
        } else {
            Flashy::error('لا يمكن مسح عرض مرتبط بحجوزات');
        }
        return redirect()->route('admin.offers');

    }

    public function view($id)
    {
        $offer = $this->getOfferByIdWithRelation($id);
        if ($offer == null)
            return view('errors.404');
        $beneficiaries = $this->getAllBeneficiaries($id);
        return view('offers.view', compact('offer', 'beneficiaries'));
    }

    public function filters()
    {
        $filters = Filter::adminSelection()->get();
        return view('offers.filters.index', compact('filters'));
    }

    public function addFilter()
    {
        $filters = Filter::active()->adminSelection()->get();
        return view('offers.filters.add', compact('filters'));
    }

    public function storeFilters(Request $request)
    {
        $rules = [
            "title_ar" => "required|max:255",
            "title_en" => "required|max:255",
            "status" => "required|in:0,1",
            "operation" => 'required|in:0,1,2,3,4,5'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Flashy::error($validator->errors()->first());
            return redirect()->back()->withErrors($validator)->withInput($request->all());
        }

        if (in_array($request->operation, [0, 1, 2])) {    // 0-> less than 1-> greater than 2-> equal to
            if (!$request->price or !is_numeric($request->price) or !$request->price > 0) {
                return redirect()->back()->withErrors(['price' => 'السعر مطلوب  مع  هذا النوع من الفلتر  '])->withInput($request->all());
            }
        }
        Filter::create($request->all());
        Flashy::success('تم اضافه عمليه الفلتره بنجاح ');
        return redirect()->route('admin.offers.filters');
    }

    public function editFilter($filterId)
    {
        $filter = Filter::find($filterId);
        if (!$filter)
            return abort('404');
        return view('offers.filters.edit', compact('filter'));
    }

    public function updateFilter($filterId, Request $request)
    {
        $rules = [
            "title_ar" => "required|max:255",
            "title_en" => "required|max:255",
            "status" => "required|in:0,1",
            "operation" => 'required|in:0,1,2,3,4,5'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Flashy::error($validator->errors()->first());
            return redirect()->back()->withErrors($validator)->withInput($request->all());
        }

        if (in_array($request->operation, [0, 1, 2])) {    // 0-> less than 1-> greater than 2-> equal to
            if (!$request->price or !is_numeric($request->price) or !$request->price > 0) {
                return redirect()->back()->withErrors(['price' => 'السعر مطلوب في هذه الحاله '])->withInput($request->all());
            }
        }
        $filter = Filter::find($filterId);
        if (!$filter)
            return abort('404');
        $filter->update($request->all());
        Flashy::success('تم التحديث بنجاح ');
        return redirect()->route('admin.offers.filters');
    }

    public function deleteFilter($filterId)
    {
        $filter = Filter::find($filterId);
        if (!$filter)
            return abort('404');
        $filter->delete();
        Flashy::success('تم حذف الفلتر بنجاح ');
        return redirect()->route('admin.offers.filters');
    }

}
