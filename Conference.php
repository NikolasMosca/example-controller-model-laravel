<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Conference
{
    private $_table = 'conferences';
    private $_optionals = 'conferences_optionals';
    private $_workshops = 'conferences_workshops';
    private $_booking = 'conferences_booking';

    //Get conference by id with optionals and workshops
    public function getById($id){
        $conference = DB::table($this->_table)
            ->select(
                "{$this->_table}.id",
                "{$this->_table}.title",
                "{$this->_table}.description",
                "{$this->_table}.image",
                "{$this->_table}.price",
                "{$this->_table}.active",
                "{$this->_table}.date_start",
                "{$this->_table}.date_end"
            )
            ->where('id', $id)
            ->first();

        if($conference){
            $conference->workshops = $this->getWorkshopById($id);
            $conference->optionals = $this->getOptionalById($id);
        }

        return $conference;
    }

    //Find a conference by booking order id
    public function findByOrderId($orderId) {
        $booking = DB::table($this->_booking)->where('order_id', $orderId)->first();
        if(!$booking) {
            return false;
        }
        return $this->getById($booking->conference_id);
    }

    //Get conference active
    public function getActive(){
        return DB::table($this->_table)->where("active", true)->first();
    }

    //Get optionals of conference selected
    public function getOptionalById($id){
        return DB::table($this->_optionals)
            ->select(
                "{$this->_optionals}.id",
                "{$this->_optionals}.title",
                "{$this->_optionals}.price",
            )
            ->where('conference_id', $id)
            ->get();
    }

    //Get workshops of conference selected
    public function getWorkshopById($id){
        return DB::table($this->_workshops)
            ->select(
                "{$this->_workshops}.id",
                "{$this->_workshops}.title",
            )
            ->where('conference_id', $id)
            ->get();
    }

    //Get all conferences
    public function getAll(){
        return DB::table($this->_table)->get();
    }

    //Create new conference
    public function create($conference){
        $id = null;
        DB::transaction(function() use($conference, &$id){
            $id = DB::table($this->_table)->insertGetId([
                'title' => $conference['title'],
                'description' => $conference['description'],
                'image' => $conference['image'],
                'price' => $conference['price'],
                'active' => $conference['active'],
                'date_start' => $conference['date_start'],
                'date_end' => $conference['date_end']
            ]);
    
            foreach($conference['workshops'] as $workshop){
                $this->createWorkshop($workshop, $id);
            }
            foreach($conference['optionals'] as $optional){
                $this->createOptional($optional, $id);
            }
        });

        return $this->getById($id);
    }

    //Manage workshop saving, if exists update it, if not exists create it, if not present delete it
    public function saveWorkshop($conference, $id){
        $updatedIds = [];
        foreach($conference['workshops'] as $workshop){
            if(!isset($workshop['id'])){
                $updatedIds[] = $this->createWorkshop($workshop, $id);
            } else {
                $updatedIds[] = $this->updateOptional($workshop, $id, $workshop['id']);
            }
        }

        $this->deleteWorkshops($id, $updatedIds);
    }

    //Manage optional saving, if exists update it, if not exists create it, if not present delete it
    public function saveOptional($conference, $id){
        $updatedIds = [];
        foreach($conference['optionals'] as $optional){
            if(!isset($optional['id'])){
                $updatedIds[] = $this->createOptional($optional, $id);
            } else {
                $updatedIds[] = $this->updateOptional($optional, $id, $optional['id']);
            }
        }

        $this->deleteOptionals($id, $updatedIds);
    }

    //Update a conference with optionals and workshops
    public function update($conference, $id){
        DB::transaction(function() use($id, $conference){
            DB::table($this->_table)->where('id', $id)->update([
                'title' => $conference['title'],
                'description' => $conference['description'],
                'image' => $conference['image'],
                'price' => $conference['price'],
                'active' => $conference['active'],
                'date_start' => $conference['date_start'],
                'date_end' => $conference['date_end']
            ]);
    
            $this->saveOptional($conference, $id);
            $this->saveWorkshop($conference, $id);
        });

        return $this->getById($id);
    }

    //Delete a conference
    public function delete($id){
        return DB::table($this->_table)->where('id', $id)->delete();
    }

    private function createWorkshop($workshop, $id){  
        return DB::table($this->_workshops)->insertGetId([
            'conference_id' => $id,
            'title' => $workshop['title']
        ]);
    }

    private function updateWorkshop($workshop, $conferenceId, $workshopId) {
        DB::table($this->_workshops)->where('conference_id', $conferenceId)->where('id', $workshopId)->update([
            'title' => $workshop['title']
        ]);
        return $id;
    }

    private function deleteWorkshops($id, $updatedIds) {
        DB::table($this->_workshops)->where('conference_id', $id)->whereNotIn('id', $updatedIds)->delete();
    }

    private function createOptional($optional, $id){
        return DB::table($this->_optionals)->insertGetId([
            'conference_id' => $id,
            'title' => $optional['title'],
            'price' => $optional['price']
        ]);
    }

    private function updateOptional($optional, $conferenceId, $optionalId) {
        DB::table($this->_optionals)->where('conference_id', $conferenceId)->where('id', $optionalId)->update([
            'title' => $optional['title'],
            'price' => $optional['price']
        ]);
        return $id;
    }

    private function deleteOptionals($id, $updatedIds) {
        DB::table($this->_optionals)->where('conference_id', $id)->whereNotIn('id', $updatedIds)->delete();
    }
}
