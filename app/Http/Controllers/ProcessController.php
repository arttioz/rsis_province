<?php

namespace App\Http\Controllers;

use App\Models\Districts;

use App\Models\EclaimData;
use App\Models\HISData;
use App\Models\ISData;
use App\Models\PoliceData;
use App\Models\PrepareMerge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

        set_time_limit(300);

        PrepareMerge::query()->truncate();



        $is_rows = $this->prepareMergeISData($month);
        $this->checkDuplicateInSameTable($is_rows,ISData::class);

        $his_rows = $this->prepareMerge43FileData($month);
        $this->checkDuplicateInSameTable($his_rows,HISData::class);

        $polich_rows = $this->preparePoliceData($month);
        $this->checkDuplicateInSameTable($polich_rows,PoliceData::class);

        $ecliam_rows = $this->prepareEclaimData($month);
        $this->checkDuplicateInSameTable($ecliam_rows,EclaimData::class);

        $this->savePrepareData($his_rows,"his",$month,2566);
        $this->savePrepareData($is_rows,"is",$month,2566);
        $this->savePrepareData($polich_rows,"police",$month,2566);
        $this->savePrepareData($ecliam_rows,"eclaim",$month,2566);

        $prepare_merge = PrepareMerge::get();
        $size = count($prepare_merge);
        $isBegin = 0;
        $policeBegin = 0;
        $eclaimBegin = 0;
        $index = 1;
        $mergeArray = [];
        foreach ($prepare_merge as $row){
            $mergeArray[$index] = [];
            $mergeArray[$index]['row'] = $row;
            $mergeArray[$index]['match'] = [];

            if ($row->table == "is" && $isBegin == 0){
                $isBegin = $index;
            }

            if ($row->table == "police" && $policeBegin == 0){
                $policeBegin = $index;
            }
            if ($row->table == "eclaim" && $eclaimBegin == 0){
                $eclaimBegin = $index;
            }

            $index++;
        }

        for($index = 1; $index < $isBegin ; $index++){
            $row = $mergeArray[$index]['row'];
            $match = $mergeArray[$index]['match'];
            $next = $index +1;

            for($search_i = $next; $search_i <= $size ; $search_i++){
                $search_r = $mergeArray[$search_i]['row'];
                $search_m = $mergeArray[$search_i]['match'];

               $check = $this->checkMatch($row,$search_r);

               if ($check > 0){
                   $match[] = $search_r->id;
                   $search_m[] = $row->id;
                   $this->updateMatch($row,$search_r);
                   $this->updateMatch($search_r,$row);
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
    public function updateMatch($row_1,$row_2){

        if ($row_1->table == "is"){
            $row_2->in_is = 1;
            $row_2->is_id = $row_1->data_id;
        }
        else if ($row_1->table == "his"){
            $row_2->in_his = 1;
            $row_2->his_id = $row_1->data_id;
        }
        else if ($row_1->table == "police"){
            $row_2->in_police = 1;
            $row_2->police_id = $row_1->data_id;
        }
        else if ($row_1->table == "eclaim"){
            $row_2->in_eclaim= 1;
            $row_2->eclaim_id = $row_1->data_id;
        }
    }

    public function savePrepareData($rows,$tableName,$month,$year){
        foreach ($rows as $row ){

            if ($row->is_duplicate != 1){
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
            if (abs($difDate) <= 7) {
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
    public function prepareMergeISData($month): Collection
    {
        $rows = ISData::whereMonth("hdate",$month)->get();
        foreach ($rows as $row) {

            $row = $this->makeISColumnForMerge($row);
        }
        return $rows;
    }


    public function prepareMerge43FileData($month): Collection
    {
        $rows = HISData::whereMonth("DATE_SERV",$month)->get();
        foreach ($rows as $row) {

            $row = $this->make43FileColumnForMerge($row);
        }
        return $rows;
    }

    public function checkDuplicateInSameTable($rows,     $model){

        $index = 1;
        $mergeArray = [];
        $size = count($rows);
        foreach ($rows as $row){
            $mergeArray[$index] = $row;

            $index++;
        }

        for($index = 1; $index <= $size ; $index++){
            $row = $mergeArray[$index];
            $nextIndex = $index + 1;

            for($search_i = $nextIndex; $search_i <= $size ; $search_i++){
                $search_r = $mergeArray[$search_i];
                $check = $this->checkMatch($row,$search_r);

                if ($check > 0){
                    $search_r->match  = $row->data_id;
                    $search_r->is_duplicate  = 1;

                    $saveRow = $model::where("id",$search_r->data_id)->first();

                    if ($saveRow == null){
                        dd($search_r,$model,$saveRow);
                    }

                    $saveRow->update(["match" => $row->data_id]);
                    $saveRow->update(["is_duplicate" => 1]);
                }
            }
        }
    }

    public function makeISColumnForMerge($row){

        $difDate = Carbon::parse($row->hdate)->diffInDays($this->dateFrom);
        $row->data_id = $row->id;
        $row->name = $row->name;
        $row->lname = $row->fname;
        $row->age = $row->age;
        $row->gender = $row->sex;

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
        $row->accdate = null;
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

    public function prepareHISData($month){

    }

    public function preparePoliceData($month){
        $police_rows = PoliceData::whereMonth("adate",$month)->get();
        foreach ($police_rows as $row){
            $nameArr = $this->seperateName($row->fullname);
            $countName = count($nameArr);

            if ($countName > 0){
                $row->name = $nameArr[0];
                if ($countName >= 2){
                    $row->lname = $nameArr[1];
                }
            }
            $row = $this->cleanNameData($row);
            $row = $this->makePoliceColumnForMerge($row);
        }
        return $police_rows;

    }


    public function prepareEclaimData($month){
        $rows = EclaimData::whereMonth("adate",$month)->get();
        foreach ($rows as $row){
            $row = $this->cleanNameData($row);
            $row = $this->makeEclaimColumnForMerge($row);
        }
        return $rows;

    }

    public function makeEclaimColumnForMerge($row){
        $difDate = Carbon::parse($row->adate)->diffInDays($this->dateFrom);
        $row->difdatefrom2000 = $difDate;

        $row->data_id = $row->id;
        $row->name = $row->name;
        $row->lname = $row->lname;
        $row->age = $row->age;
        if ($row->sex == "ชาย"){
            $row->gender = 1;
        }else{
            $row->gender = 2;
        }

        $row = $this->cleanNameData($row);
        $row->name_lenght = strlen($row->name);
        $row->is_cid_good = ($row->cid != null);
        $row->cid_num = intval($row->cid);
        $row->is_confirm_thai = ctype_digit($row->cid);
        $row->accdate = $row->adate;
        $row->hospdate = null;
        $row->hospcode = $row->hospcode;

        return  $row;
    }

    public function makePoliceColumnForMerge($row){
        $difDate = Carbon::parse($row->adate)->diffInDays($this->dateFrom);
        $row->difdatefrom2000 = $difDate;

        $row->data_id = $row->id;
        $row->name = $row->name;
        $row->lname = $row->lname;
        $row->age = $row->age;
        if ($row->sex == "ชาย"){
            $row->gender = 1;
        }else{
            $row->gender = 2;
        }

        $row = $this->cleanNameData($row);
        $row->name_lenght = strlen($row->name);
        $row->is_cid_good = ($row->cid != null);
        $row->cid_num = intval($row->cid);
        $row->is_confirm_thai = ctype_digit($row->cid);
        $row->accdate = $row->adate;
        $row->hospdate = null;
        $row->hospcode = null;

        return  $row;
    }

    public function seperateName($name){
//        $maleFrontName = ['นาย', 'ด.ช.', 'ดช.', 'เด็กชาย', 'Mr', 'พระ'];
//        $femaleFrontName = ['Ms', 'Mrs', 'นาง','นางสาว', 'น.ส.', 'ด.ญ.', 'นส.', 'ดญ.', 'หญิง', "แม่"];
        $prename = ['เด็กชาย','พล.ร.ท.','พล.ร.อ.','พล.ร.ต.','พล.อ.อ.','พล.อ.ต.','พล.อ.ท.','พล.ต.อ.','พล.ต.ต.','พล.ต.ท.','นางสาว','จ.ส.ท.','จ.ส.อ.','จ.ส.ต.','พ.จ.อ.','พ.จ.ต.','พ.จ.ท.','พ.อ.ท.','พ.อ.อ.','พ.อ.ต.','พ.ต.ท.','ร.ต.อ.','ร.ต.ต.','จ.ส.ต.','ส.ต.ท.','พ.ต.อ.','พ.ต.ต.','ร.ต.ท.','ส.ต.อ.','ส.ต.ต.','ม.ร.ว.','พล.อ.','พล.ต.','พล.ท.','น.ส.','ด.ญ.','หญิง','ด.ช.','พ.ท.','ร.อ.','ร.ต.','ส.อ.','ส.ต.','พ.อ.','พ.ต.','ร.ท.','ส.ท.','จ.ท.','จ.อ.','จ.ต.','น.ท.','ร.อ.','ร.ต.','จ.อ.','จ.ต.','น.อ.','น.ต.','ร.ท.','จ.ท.','ม.ล.','ด.ต.','แม่','Mrs','นส.','ดญ.','นาย','ดช.','พระ','นาง','พลฯ','Mr','Ms'];

        $name = str_replace($prename, "", $name);
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $splitName = explode(" ",$name);

        return $splitName;
    }
}
