<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Conference;
use App\Models\User;
use App\Models\Price;
use App\Models\Booking;
use Exception;
use Validator;

class ConferenceController extends Controller {
    //Get active conference
    public function getActive(){
        $conferenceModel = new Conference;
        $conference = $conferenceModel->getActive();

        if(!$conference){
            return response()->json([
                'status' => 'error',
                'message' => 'Active conference not found',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //Discount 
        $priceModel = new Price();
        $pricesList = $priceModel->get();
        $prices = [];
        foreach($pricesList as $price) {
            $prices[$price->code] = $price->price;
        }
        $conference->discount_price = $conference->price;
        $conference->discount_price_guests = $prices['PRICE_CONFERENCE_GUESTS'];
        if($this->_userData) {
            if($this->_userData->membership) {
                if($this->_userData->profile_name === 'client') {
                    $conference->discount_price = $prices['PRICE_CONFERENCE_MEMBER'];
                }
                if($this->_userData->profile_name === 'agent' || $this->_userData->profile_name === 'expert') {
                    $conference->discount_price = $prices['PRICE_CONFERENCE_EXPERT'];
                }
            }
        } 
        
        $conference->workshops = $conferenceModel->getWorkshopById($conference->id);
        $conference->optionals = $conferenceModel->getOptionalById($conference->id);
        
        return response()->json([
            'status' => 'success',
            'data' => $conference
        ]);
    }

    //List all conferences
    public function index()
    {
        $conferenceModel = new Conference;
        $conferences = $conferenceModel->getAll();
        
        return response()->json([
            'status' => 'success',
            'conferences' => $conferences
        ]);
    }

    //Store a new conference
    public function store(Request $request)
    {
        $data = Validator::make($request->all(), [
            'conference.title' => 'required|string',
            'conference.description' => 'required|string',
            'conference.image' => 'required|string',
            'conference.price' => 'required|numeric',
            'conference.active' => 'required|boolean',
            'conference.date_start' => 'required|date_format:Y-m-d',
            'conference.date_end' => 'required|date_format:Y-m-d|after:conference.date_start',
            'conference.workshops.*.title' => 'required|string',
            'conference.optionals.*.title' => 'required|string',
            'conference.optionals.*.price' => 'required|numeric'
        ]);

        $messages = $data->errors();
        
        if ($data->fails()) {
            return response()->json([
                "status" => "error",
                "message" => $messages->first(), 
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        };

        $data = $request->all();

        $conference = [];
        try{
            $conferenceModel = new Conference;
            $conference = $conferenceModel->create($data['conference']);
        } catch(Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'conference' => $conference
        ]);
    }

    //Show a conference
    public function show($id)
    {
        $conferenceModel = new Conference;
        $conference = $conferenceModel->getById($id);

        if(!$conference){
            return response()->json([
                'status' => 'error',
                'message' => 'Conference not found',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'status' => 'success',
            'conference' => $conference
        ]);
    }

    //Update a conference
    public function update(Request $request, $id)
    {
        $data = Validator::make($request->all(), [
            'conference.title' => 'required|string',
            'conference.description' => 'required|string',
            'conference.image' => 'required|string',
            'conference.price' => 'required|numeric',
            'conference.active' => 'required|boolean',
            'conference.date_start' => 'required|date_format:Y-m-d',
            'conference.date_end' => 'required|date_format:Y-m-d|after:conference.date_start',
            'conference.workshops.*.title' => 'required|string',
            'conference.optionals.*.id' => 'numeric',
            'conference.optionals.*.title' => 'required|string',
            'conference.optionals.*.price' => 'required|numeric'
        ]);

        $messages = $data->errors();
        
        if ($data->fails()) {
            return response()->json([
                "status" => "error",
                "message" => $messages->first(), 
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        };

        $data = $request->all();

        $conference = [];

        try{
            $conferenceModel = new Conference;
            $conference = $conferenceModel->update($data['conference'], $id);
        } catch(Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 'success',
            'conference' => $conference
        ]);

    }

    //Delete a confernce
    public function destroy($id)
    {
        $conferenceModel = new Conference;
        $conference = $conferenceModel->delete($id);

        return response()->json([
            'status' => 'success',
            'message' => 'conference deleted successfully'
        ]);
    }

    //Booking the conference for multiple users
    public function bookingConference(Request $request){
        $data = Validator::make($request->all(),[
            'add_users.*.name' => 'required|string',
            'add_users.*.surname' => 'required|string',
            'add_users.*.phone' => 'string|nullable',
            'add_users.*.email' => 'required|email:rfc,dns',
            'add_users.*.password' => 'nullable|string|min:8',
            'add_users.*.password_confirm' => 'required_with:add_users.*.password|same:add_users.*.password|min:8',
            'add_users.*.privacy_policy' => 'required|boolean|in:true,1',
            'conferences' => 'required',
            'conferences.*.id' => 'required|numeric',
            'conferences.*.email' => 'required|email:rfc,dns',
            'conferences.*.optionals.*.id' => 'required|numeric',
            'conferences.*.optionals.*.quantity' => 'required|numeric',
            'conferences.*.workshops.*' => 'required|numeric'
         ]);
         
         $messages = $data->errors();
         
         if ($data->fails()) {
             return response()->json([
                 "status" => "error",
                 "message" => $messages->first(), 
             ], Response::HTTP_UNPROCESSABLE_ENTITY);
         };
 
         $data = $request->all();

         $employees = [];
         $orderId = null;
         try{
            DB::transaction(function() use($data, &$employees, &$orderId){
                $userModel = new User;
                $loggedUser= $this->_userData;
                $companyId = $userModel->getCompanyId($loggedUser->id);
                
                if(isset($data['add_users'])){
                    foreach($data['add_users'] as $employee){
                        $isNewUser = !$userModel->exist($employee['email']);
                        if($isNewUser && !isset($employee['password'])) {
                            throw new Exception("This email {$employee['email']} is new and password is required");
                        }
                        if($isNewUser) {
                            $employee['founder'] = 0;
                            $userModel->create($employee, $companyId);
                        } 
                    }
                }

                $bookingModel = new Booking;
                $orderId = $bookingModel->createOrderId();
                foreach($data['conferences'] as $conference){
                    $user = $userModel->getUserByEmail($conference['email']);
                    if($user) {
                        $bookingId = $bookingModel->addBooking($conference['id'], $user->id, $orderId);
                        foreach($conference['optionals'] as $optional){
                            $bookingModel->addOptional($bookingId, $optional);
                        }
                        foreach($conference['workshops'] as $workshop){
                            $bookingModel->addWorkshop($bookingId, $workshop);
                        }
                    }          
                }
            });
        } catch(Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Conference booked successfully',
            'order_id' => $orderId
        ]);
    }
}
