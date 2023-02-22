<?php

namespace App\Http\Controllers;

use App\Models\Districts;

use App\Models\HISData;
use App\Models\ISData;
use App\Models\PrepareMerge;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

use function PHPUnit\Framework\isNull;

use DB;
use Hash;


class ProcessController extends Controller
{
    public $dateFrom;

    public function __construct()
    {
        $this->dateFrom = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', '2000-01-01 00:00:00');
    }


    public function mergeRSISinHosp(Request $request, $month){

        PrepareMerge::query()->truncate();

        $is_rows = ISData::whereMonth("hdate",$month)->get();
        $his_rows = HISData::whereMonth("DATE_SERV",$month)->get();

        $is_rows = $this->prepareMergeISData($is_rows);
        $his_rows = $this->prepareMerge43FileData($his_rows);

        $this->savePrepareData($his_rows,"his",$month,2566);
        $this->savePrepareData($is_rows,"is",$month,2566);

        $prepare_merge = PrepareMerge::get();
        $size = count($prepare_merge);
        $isBegin = 0;
        $index = 1;
        $mergeArray = [];
        foreach ($prepare_merge as $row){
            $mergeArray[$index] = [];
            $mergeArray[$index]['row'] = $row;
            $mergeArray[$index]['match'] = [];

            if ($row->table == "is" && $isBegin == 0){
                $isBegin = $index;
            }
            $index++;
        }

        for($index = 1; $index < $isBegin ; $index++){
            $row = $mergeArray[$index]['row'];
            $match = $mergeArray[$index]['match'];

            for($search_i = $isBegin; $search_i <= $size ; $search_i++){
                $search_r = $mergeArray[$search_i]['row'];
                $search_m = $mergeArray[$search_i]['match'];


               $check = $this->checkMatch($row,$search_r);


               if ($check > 0){
                   $match[] = $search_r->id;
                   $search_m[] = $row->id;
               }
                $mergeArray[$search_i]['match'] = $search_m;
            }

            $mergeArray[$index]['match'] = $match;

        }



        foreach ($mergeArray as $data){
            $row = $data['row'];
            $row->match_id = implode(",",$data['match']) ;
            $row->save();
        }

    }

    public function savePrepareData($rows,$tableName,$month,$year){
        foreach ($rows as $row ){

            $prepareMerge = new PrepareMerge();
            $prepareMerge->data_id = $row->id;
            $prepareMerge->name = $row->name;
            $prepareMerge->lname = $row->lname;
            $prepareMerge->age = $row->age;
            $prepareMerge->gender = $row->gender;
            $prepareMerge->difdatefrom2000 = $row->difdatefrom2000;
            $prepareMerge->name_lenght = $row->name_lenght;
            $prepareMerge->is_cid_good	 = $row->is_cid_good;
            $prepareMerge->cid_num  = $row->cid_num;
            $prepareMerge->is_confirm_thai	 = $row->is_confirm_thai;
            $prepareMerge->month = $month;
            $prepareMerge->year = $year;
            $prepareMerge->table = $tableName;
            $prepareMerge->accdate = $row->accdate;
            $prepareMerge->hospdate = $row->hospdate;
            $prepareMerge->hospcode = $row->hospcode;
            $prepareMerge->save();
        }
    }

    public function checkMatch($row_1, $row_2)
    {
        $matchResult = "";

        $aDateMatch = false;
        $IDMatch = false;
        $nameMatch = false;
        $bodyMatch = false;
        $nameAndBodyMatch = false;

        //54 -> 43 ->45
        if ($row_1->is_cid_good) {

            if ($row_1->is_confirm_thai) {
                if ($row_1->cid_num === $row_2->cid_num) {
                    $IDMatch = true;
                }
            } else {
                if ($row_1->cid === $row_2->cid) {
                    $IDMatch = true;
                }
            }
        }

        // 81 -> 73-> 67 -> 60 -> 40

        if ($row_1->name == $row_2->name) {

            if ($row_1->name == $row_2->name) {

                if ($row_1->lname == $row_2->lname) {
                    $nameMatch = true;
                }
            }
        }

        if ($row_1->age == $row_2->age){
            if ($row_1->gender == $row_2->gender){
                $bodyMatch = true;
            }
        }

        if ($nameMatch and $bodyMatch){
            $nameAndBodyMatch = true;
        }

        if ($IDMatch || $nameAndBodyMatch) {

            $difDate =  $row_1->difdatefrom2000 - $row_2->difdatefrom2000;
            if (abs($difDate) <= 2) {
                $aDateMatch = true;
            }
        }

        // 1.1 ID และ วันเกิดเหตุ
        if ($IDMatch && $aDateMatch) {
            $matchResult = 1;
        }

//        // 1.2 ชื่อ-สกุล และ วันเกิดเหตุ และ จังหวัดเกิดเหตุ
//        else  if ($nameMatch && $aDateMatch) {
//            $matchResult = 2;
//        }

        // 1.3 ชื่อ-สกุล และ วันเกิดเหตุ
        else if ($nameAndBodyMatch && $aDateMatch) {
            $matchResult = 3;
        }

//        // 1.4 ชื่อ-สกุล และ จังหวัด
//        else if ($nameMatch && $provMatch) {
//
//            $matchResult = 4;
//        }
        // 1.5 ID
        else if ($IDMatch) {
            $matchResult = 5;
        }

        // 1.6 ชื่อ-สกุล ตัว
        else  if ($nameAndBodyMatch) {
            $matchResult = 6;
        }

        return $matchResult;
    }

    /**
     * @param Collection $rows // List of IS data without key value for merging
     * @return Collection $rows // List of IS data with key value for merging
     */
    public function prepareMergeISData(Collection $rows): Collection
    {

        foreach ($rows as $row) {

            $row = $this->makeISColumnForMerge($row);
        }
        return $rows;
    }


    public function prepareMerge43FileData(Collection $rows): Collection
    {

        foreach ($rows as $row) {

            $row = $this->make43FileColumnForMerge($row);
        }
        return $rows;
    }


    public function makeISColumnForMerge($row){

        $difDate = Carbon::parse($row->hdate)->diffInDays($this->dateFrom);
        $row->difdatefrom2000 = $difDate;
        $row->data_id = $row->id;
        $row->name = $row->name;
        $row->lname = $row->fname;
        $row->age = $row->age;
        $row->gender = $row->sex;

        $row = $this->cleanNameData($row);
        $row->name_lenght = strlen($row->NAME);
        $row->is_cid_good = ($row->pid != null);
        $row->cid_num = intval($row->pid);
        $row->is_confirm_thai = ctype_digit($row->pid);
        $row->accdate = $row->adate;
        $row->hospdate = $row->hdate;
        $row->hospcode = $row->hosp;

        return  $row;
    }

    public function make43FileColumnForMerge($row){
        $difDate = Carbon::parse($row->DATE_SERV)->diffInDays($this->dateFrom);
        $row->difdatefrom2000 = $difDate;

        $row->data_id = $row->id;
        $row->name = $row->NAME;
        $row->lname = $row->LNAME;
        $row->age = $row->AGE;
        $row->gender = $row->sex;

        $row = $this->cleanNameData($row);
        $row->name_lenght = strlen($row->NAME);
        $row->is_cid_good = ($row->CID != null);
        $row->cid_num = intval($row->CID);
        $row->is_confirm_thai = ctype_digit($row->CID);
        $row->accdate = $row->null;
        $row->hospdate = $row->DATE_SERV;
        $row->hospcode = $row->HOSPCODE;

        return  $row;
    }



    public function cleanNameData($row){

        // CID Fname Lname Remove Special word - Spacbar
        $row->cid =   str_replace(' ', '', preg_replace('/[^A-Za-z0-9\-]/', '', $row->cid));
        $row->name =   str_replace(' ', '', preg_replace('/\s+/', '', $row->name));
        $row->lname =   str_replace(' ', '', preg_replace('/\s+/', '', $row->lname));

        /*  เอาสระ อะ อา อิ อี ออกจากหน้าคำ */
        //ถ้าชื่อคำแรกเป็นสระ
        $begin_wrong = ['ิ', 'ฺ.', '์', 'ื', '่', '๋', '้', '็', 'ั', 'ี', '๊', 'ุ', 'ู', 'ํ'];
        while (in_array(mb_substr($row->name, 0, 1), $begin_wrong)) {
            $row->name =  mb_substr($row->name, 1);
        }


        //ถ้าสกุลคำแรกเป็นสระ
        while (in_array(mb_substr($row->lname, 0, 1), $begin_wrong)) {
            $row->lname = mb_substr($row->lname, 1);
        }

        $row->name =  $this->replaceName($row->name);
        $row->lname =  $this->replaceName($row->lname);

        return $row;
    }

    public function replaceName($name){

        $name = str_replace( "ร"   , "ล",$name);
        $name = str_replace( "ณ"   , "น",$name);
        $name = str_replace( "ศ"   , "ส",$name);
        $name = str_replace( "ษ"   , "ส",$name);
        $name = str_replace( "ฌ"   , "ช",$name);
        $name = str_replace(  "์"   , "",$name );
        $name = str_replace(  "ู"  , "ุ" ,$name );
        $name = str_replace(  "ี"  , "ิ" ,$name );

        return $name;
    }
}
