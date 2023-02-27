<?php

namespace App\Http\Controllers;

use App\Models\Districts;

use App\Models\EclaimData;
use App\Models\HISData;
use App\Models\IntegrateFinal;
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
    public $vehicleTxt;

    public function __construct()
    {
        $this->dateFrom = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', '2000-01-01 00:00:00');

        $walkNum = 0;
        $bycicleNum = 1;
        $motorcycleNum = 2;
        $tricycleNum = 3;
        $carNum = 4;
        $truckNum = 5;
        $bigTruckNum = 6;
        $busNum = 7;
        $this->vehicleTxt = [];
        $this->vehicleTxt[$walkNum] = "เดินเท้า";
        $this->vehicleTxt[$bycicleNum] = "กรยาน";
        $this->vehicleTxt[$motorcycleNum] = "รถจักรยานยนต์";
        $this->vehicleTxt[$tricycleNum] = "ยานยนต์สามล้อ";
        $this->vehicleTxt[$carNum] = "รถยนต์";
        $this->vehicleTxt[$truckNum] = "รถบรรทุกเล็กหรือรถตู้";
        $this->vehicleTxt[$bigTruckNum] = "รถบรรทุกหนัก";
        $this->vehicleTxt[$busNum] = "รถโดยสาร";

    }


    public function mergeRSISinHosp(Request $request, $startDate,$endDate){

        set_time_limit(300);
        ini_set('memory_limit', '6144M');

        PrepareMerge::query()->truncate();

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $is_rows = $this->prepareMergeISData($startDate,$endDate);
        $this->checkDuplicateInSameTable($is_rows,ISData::class);

        $his_rows = $this->prepareMerge43FileData($startDate,$endDate);
        $this->checkDuplicateInSameTable($his_rows,HISData::class);

        $ecliam_rows = $this->prepareEclaimData($startDate,$endDate);
        $this->checkDuplicateInSameTable($ecliam_rows,EclaimData::class);

        $polich_rows = $this->preparePoliceData($startDate,$endDate);
        $this->checkDuplicateInSameTable($polich_rows,PoliceData::class);

        $this->savePrepareData($his_rows);
        $this->savePrepareData($is_rows);
        $this->savePrepareData($ecliam_rows);
        $this->savePrepareData($polich_rows);


        $prepare_merge = PrepareMerge::get();
        $size = count($prepare_merge);

        $isBegin = 0;
        $policeBegin = 0;
        $eclaimBegin = 0;
        $index = 1;
        $mergeArray = [];
        $matchRow = [];
        $matchedRowId = [];
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


        // Start Match
        for($index = 1; $index < $size ; $index++){

            $row = $mergeArray[$index]['row'];
            $match = $mergeArray[$index]['match'];

            $next = $index +1;

            if (!array_key_exists($row->id,$matchedRowId)){
                $matchRow[$row->id] = [];
                $matchRow[$row->id][] = $row;
            }

            for($search_i = $next; $search_i <= $size ; $search_i++){
                $search_r = $mergeArray[$search_i]['row'];
                $search_m = $mergeArray[$search_i]['match'];

               $check = $this->checkMatch($row,$search_r);

               if ($check['result'] > 0){
                   $match[] = $search_r->id;
                   $search_m[] = $row->id;
                   $this->updateMatch($row,$search_r,$check['log']);
                   $this->updateMatch($search_r,$row,$check['log']);

                   if (!array_key_exists($search_r->id,$matchedRowId)){
                       // Keep Match ID for check
                       $matchedRowId[$search_r->id] = $search_r->id;
                       $matchRow[$row->id][] = $search_r;
                   }
               }else{

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

        $this->writeFinalIntegrate($matchRow,$is_rows,$his_rows,$polich_rows,$ecliam_rows);
    }



    public function writeFinalIntegrate($matchRow,$is_rows,$his_rows,$police_rows,$eclaim_rows){

        IntegrateFinal::query()->truncate();

        $isArr = $this->rowsToArrayKey($is_rows);
        $hisArr = $this->rowsToArrayKey($his_rows);
        $policeArr = $this->rowsToArrayKey($police_rows);
        $eclaimArr = $this->rowsToArrayKey($eclaim_rows);


        foreach ($matchRow as $mainRows){
            $integrateRow = new IntegrateFinal();

            foreach ($mainRows as $row){
                $id = $row->data_id;

                if ($row->table == "his"){
                    $dataRow = $hisArr[$id];
                    $integrateRow->his_id = $dataRow->data_id;

                }elseif ($row->table == "is"){
                    $dataRow = $isArr[$id];
                    $integrateRow->is_id = $dataRow->data_id;

                }elseif ($row->table == "police"){
                    $dataRow = $policeArr[$id];
                    $integrateRow->police_id = $dataRow->data_id;
                    $integrateRow->police_event_id = $dataRow->event_id;

                }elseif ($row->table == "eclaim"){
                    $dataRow = $eclaimArr[$id];
                    $integrateRow->eclaim_id = $dataRow->data_id;
                }

                $this->assignValue($integrateRow,$dataRow,"name","nameSave");
                $this->assignValue($integrateRow,$dataRow,"lname","lnameSave");
                $this->assignValue($integrateRow,$dataRow,"cid");
                $this->assignValue($integrateRow,$dataRow,"gender");
                $this->assignValue($integrateRow,$dataRow,"nationality");
                $this->assignValue($integrateRow,$dataRow,"dob");
                $this->assignValue($integrateRow,$dataRow,"age");
                $this->assignValue($integrateRow,$dataRow,"hdate");
                $this->assignValue($integrateRow,$dataRow,"is_death");
                $this->assignValue($integrateRow,$dataRow,"occupation");
                $this->assignValue($integrateRow,$dataRow,"alcohol");
                $this->assignValue($integrateRow,$dataRow,"belt_risk");
                $this->assignValue($integrateRow,$dataRow,"helmet_risk");
                $this->assignValue($integrateRow,$dataRow,"roaduser");
                $this->assignValue($integrateRow,$dataRow,"vehicle_1");
                $this->assignValue($integrateRow,$dataRow,"vehicle_plate_1");
                $this->assignValue($integrateRow,$dataRow,"accdate");
                $this->assignValue($integrateRow,$dataRow,"atumbol");
                $this->assignValue($integrateRow,$dataRow,"aaumpor");
                $this->assignValue($integrateRow,$dataRow,"aprovince");
                $this->assignValue($integrateRow,$dataRow,"vehicle_2");
                $this->assignValue($integrateRow,$dataRow,"hospcode");
                $this->assignValue($integrateRow,$dataRow,"alat");
                $this->assignValue($integrateRow,$dataRow,"along");

            }
            $integrateRow->save();
        }
    }

    private function assignValue($finalData,$dataRow,$colName,$dataColName = ""){

        if ($dataColName == ""){
            $dataColName = $colName;
        }

        $currValue = $finalData->{$colName};
        if ($currValue == null && $dataRow->{$dataColName} != null){
            $finalData->{$colName} = $dataRow->{$dataColName};
        }
    }


    private function rowsToArrayKey($rows){
        $arr = [];
        foreach ($rows as $row){
            $arr[$row->data_id] = $row;
        }
        return $arr;
    }



    public function updateMatch($row_1,$row_2,$log){

        if ($row_1->table == "is"){
            $row_2->in_is = 1;
            $row_2->is_id = $row_1->data_id;
            $row_2->is_log = $log;
        }
        else if ($row_1->table == "his"){
            $row_2->in_his = 1;
            $row_2->his_id = $row_1->data_id;
            $row_2->his_log = $log;
        }
        else if ($row_1->table == "police"){
            $row_2->in_police = 1;
            $row_2->police_id = $row_1->data_id;
            $row_2->police_log = $log;
        }
        else if ($row_1->table == "eclaim"){
            $row_2->in_eclaim= 1;
            $row_2->eclaim_id = $row_1->data_id;
            $row_2->eclaim_log = $log;
        }
    }

    public function savePrepareData($rows){
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
                $prepareMerge->vehicle_type	 = $row->vehicle_type;
//                if ($row->adate != null){
//                    $date = Carbon::parse($row->accdate);
//                }else{
//                    $date = Carbon::parse($row->hospdate);
//                }
//                $prepareMerge->month = $date->month;
//                $prepareMerge->year = $date->year;

                $prepareMerge->table = $row->table;
                $prepareMerge->accdate = $row->accdate;
                $prepareMerge->hospdate = $row->hospdate;
                $prepareMerge->hospcode = $row->hospcode;
                $prepareMerge->save();
            }
        }
    }

    public function getVehicleType($row){

//        V01-V09 	เดินเท้า
//        V10-V19	จักรยาน
//        V20-V29	รถจักรยานยนต์
//        V30-V39	ยานยนต์สามล้อ
//        V40-V49	รถยนต์
//        V50-V59	รถบรรทุกเล็กหรือรถตู้
//        V60-V69	รถบรรทุกหนัก
//        V70-V79	รถโดยสาร
//        V80-V89	อุบัติเหตุการขนส่งทางบกอื่น
//        V90-V94	อุบัติเหตุการขนส่งทางอื่นๆ


        $vehicle_type = 0;

        $walkNum = 0;
        $bycicleNum = 1;
        $motorcycleNum = 2;
        $tricycleNum = 3;
        $carNum = 4;
        $truckNum = 5;
        $bigTruckNum = 6;
        $busNum = 7;

        $walkTxt = "เดิน";
        $bycicleTxt = "จักรยาน";
        $motorcycleTxt = "จักรยานยนต์";
        $tricycleTxt = "สามล้อ";
        $carTxt = "รถยนต์"; // ปิกอั๊พ รถแท็กซี่
        $vanTxt = "รถตู้"; // รถตู้ทั่วไป รถตู้โดยสารประจำทาง รถตู้สาธารณะอื่นๆ
        $truckTxt = "รถกระบะ"; // รถตู้ทั่วไป รถตู้โดยสารประจำทาง รถตู้สาธารณะอื่นๆ
        $bigTruckTxt = "รถบรรทุก"; //รถพ่วง รถบรรทุกหนัก
        $veryBigTruckTxt = "รถพ่วง"; //รถพ่วง รถบรรทุกหนัก
        $busTxt = "รถบัส"; //โดยสาร
        $omniBusTxt = "รถโดยสาร"; //โดยสาร
        $schoolBusTxt = "รถรับส่งนักเรียน"; // รถรับส่งนักเรียน


        if ($row->table == "his"){
           $code = strtoupper($row->DIAGCODE) ;

                if (str_contains($code,"V0")) $vehicle_type = $walkNum;
           else if (str_contains($code,"V1")) $vehicle_type = $bycicleNum;
           else if (str_contains($code,"V2")) $vehicle_type = $motorcycleNum;
           else if (str_contains($code,"V3")) $vehicle_type = $tricycleNum;
           else if (str_contains($code,"V4")) $vehicle_type = $carNum;
           else if (str_contains($code,"V5")) $vehicle_type = $truckNum;
           else if (str_contains($code,"V6")) $vehicle_type = $bigTruckNum;
           else if (str_contains($code,"V7")) $vehicle_type = $busNum;

        }

        if ($row->table == "is"){
//            01	    จักรยาน/สามล้อ
//            02		จักรยานยนต์
//            03		สามล้อเครื่อง
//            04		รถเก๋ง
//            05		ปิกอั๊พ
//            06		รถบรรทุกหนัก
//            07		รถพ่วง
//            08		รถโดยสารสองแถว
//            09		รถโดยสารบัส
//            10		รถแท็กซี่
//            11		รถไฟ
//            12		สัตว์ รถเทียมสัตว์
//            13		เครื่องบิน เฮลิค็อปเตอร์ เครื่องร่อน
//            14		เรือทุกชนิด
//            15		รถใช้งานเกษตรกรรม
//            16		รถอีแต๋น
//            17		รถสกายแล็ป
//            18		รถตู้ทั่วไป
//            181		รถตู้โดยสารประจำทาง
//            182		รถตู้สาธารณะอื่นๆ
//            19		รถพยาบาล
//            191		รถ Refer
//            192		รถกู้ชีพ (รถโรงพยาบาล)
//            193		รถกู้ชีพ (ไม่ใช่รถโรงพยาบาล)
//            99		อื่นๆ
            $codeW = $row->injp ;
            $code = $row->injt ;

            if ($codeW == 1) $vehicle_type = $walkNum;
            else if ($code == 1) $vehicle_type = $walkNum;          // 01	    จักรยาน/สามล้อ
            else if ($code == 2) $vehicle_type = $motorcycleNum;    // 02		จักรยานยนต์
            else if ($code == 3) $vehicle_type = $tricycleNum;      // 03		สามล้อเครื่อง
            else if ($code == 4) $vehicle_type = $carNum;           // 04		รถเก๋ง
            else if ($code == 5) $vehicle_type = $truckNum;         // 05		ปิกอั๊พ
            else if ($code == 6) $vehicle_type = $bigTruckNum;      // 06		รถบรรทุกหนัก
            else if ($code == 7) $vehicle_type = $bigTruckNum;      // 07		รถพ่วง
            else if ($code == 8) $vehicle_type = $busNum;           // 08		รถโดยสารสองแถว
            else if ($code == 9) $vehicle_type = $busNum;           // 09		รถโดยสารบัส
            else if ($code == 10) $vehicle_type = $carNum;          // 10		รถแท็กซี่
            else if ($code == 18) $vehicle_type = $truckTxt;        // 18		รถตู้ทั่วไป

        }

        if ($row->table == "eclaim" || $row->table == "police"){

            if ($row->table == "eclaim"){
                $code = strtoupper($row->vehicle_type) ;
            }else{
                $code = strtoupper($row->vehicle) ;
            }

            if (str_contains($walkTxt,$code)) $vehicle_type = 0;
            else if (str_contains($motorcycleTxt,$code)) $vehicle_type = 2;
            else if (str_contains($bycicleTxt,$code)) $vehicle_type = 1;
            else if (str_contains($tricycleTxt,$code)) $vehicle_type = 3;
            else if (str_contains($carTxt,$code)) $vehicle_type = 4;

            else if (str_contains($vanTxt,$code)) $vehicle_type = 5;
            else if (str_contains($truckTxt,$code)) $vehicle_type = 5;

            else if (str_contains($bigTruckTxt,$code)) $vehicle_type = 6;
            else if (str_contains($veryBigTruckTxt,$code)) $vehicle_type = 6;

            else if (str_contains($busTxt,$code)) $vehicle_type = 7;
            else if (str_contains($omniBusTxt,$code)) $vehicle_type = 7;
        }

        return $vehicle_type;
    }

    public function checkMatch($row_1, $row_2)
    {
        $matchResult = 0;
        $matchLog = 0;

        $aDateMatch = false;
        $aDateSameMatch = false;
        $IDMatch = false;
        $nameMatch = false;
        $bodyMatch = false;
        $vehicleMatch = false;
        $nameAndBodyMatch = false;

        //54 -> 43 ->45
        if ($row_1->is_cid_good == 1 &&  $row_2->is_cid_good  == 1) {

            if ($row_1->is_confirm_thai) {
                if ($row_1->cid_num - $row_2->cid_num == 0) {
                    $IDMatch = true;
                }
            } else {
                if ($row_1->cid_num == $row_2->cid_num) {
                    $IDMatch = true;
                }
            }
        }

        if ($row_1->vehicle_type == $row_2->vehicle_type){
            $vehicleMatch = true;
        }

        // 81 -> 73-> 67 -> 60 -> 40

        if ($row_1->name == $row_2->name && strlen($row_1->name) > 0) {

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


        $difDate =  $row_1->difdatefrom2000 - $row_2->difdatefrom2000;
        if (abs($difDate) <= 7) {
            $aDateMatch = true;
        }

        if (abs($difDate) <= 1){
            $aDateSameMatch = true;
        }

        $matchTxt = "$row_1->data_id ($row_1->table) = $row_2->data_id ($row_2->table):";

        // 1 ID และ ชื่อ-สกุล  และ วันที่เกิดเหตุ/วันที่มารับบริการที่รพ. (ช่วงระยะเวลาภายใน 7 วัน) และ พาหนะ
        if ($IDMatch && $nameMatch && $aDateMatch && $vehicleMatch) {
            $matchResult = 1;
            $matchLog = $matchTxt.": 1 Protocal ID,NAME,DATE(7),VEHICLE";
        }

        // 2 ID  และ วันที่เกิดเหตุ/วันที่มารับบริการที่รพ. (ช่วงระยะเวลาภายใน 7 วัน) และ พาหนะ
        else if ($IDMatch && $aDateMatch && $vehicleMatch) {
            $matchResult = 2;
            $matchLog = $matchTxt.": 2 Protocal ID,DATE(7),VEHICLE";
        }

        // 3 ID  และ วันที่เกิดเหตุ/วันที่มารับบริการที่รพ. (ช่วงระยะเวลาภายใน 7 วัน) และ พาหนะ
        else if ($nameMatch && $aDateMatch && $vehicleMatch) {
            $matchResult = 3;
            $matchLog = $matchTxt.": 3 Protocal NAME,DATE(7),VEHICLE";
        }

        // 4 ID และ วันที่เกิดเหตุ/วันที่มารับบริการที่รพ. *วันเดียวกัน
        else if ($IDMatch && $aDateSameMatch ) {
            $matchResult = 4;
            $matchLog = $matchTxt.": 4 Protocal ID,DATE(0)";
        }

        // 5 ชื่อ สกุล และวันที่เกิดเหตุ/วันที่มารับบริการที่รพ. *วันเดียวกัน
        else if ($nameMatch && $aDateSameMatch ) {
            $matchResult = 5;
            $matchLog = $matchTxt.": 5 Protocal NAME,DATE(0)";
        }

        // 6 ID และ พาหนะ
        else if ($IDMatch && $vehicleMatch ) {
            $matchResult = 6;
            $matchLog = $matchTxt.": 6 Protocal ID,VEHICLE";
        }

        // 7 ชื่อ สกุล และ พาหนะ
        else if ($nameMatch && $vehicleMatch ) {
            $matchResult = 7;
            $matchLog = $matchTxt.": 7 Protocal NAME,VEHICLE";
        }

        $matchArr = [];
        $matchArr['result'] = $matchResult;
        $matchArr['log'] = $matchLog;


        return $matchArr;
    }

    /**
     * @param Collection $rows // List of IS data without key value for merging
     * @return Collection $rows // List of IS data with key value for merging
     */
    public function prepareMergeISData($startDate,$endDate): Collection
    {
        $rows = ISData::whereBetween("hdate",[$startDate,$endDate])->get();
        foreach ($rows as $row) {

            $row = $this->makeISColumnForMerge($row);
        }
        return $rows;
    }


    public function prepareMerge43FileData($startDate,$endDate): Collection
    {
        $rows = HISData::whereBetween("DATE_SERV",[$startDate,$endDate])->get();
        foreach ($rows as $row) {

            $row = $this->make43FileColumnForMerge($row);
        }
        return $rows;
    }

    public function preparePoliceData($startDate,$endDate){
        $police_rows = PoliceData::whereBetween("adate",[$startDate,$endDate])->with("policeEvent")->get();

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


    public function prepareEclaimData($startDate,$endDate){
        $rows = EclaimData::whereBetween("adate",[$startDate,$endDate])->get();
        foreach ($rows as $row){
            $row = $this->cleanNameData($row);
            $row = $this->makeEclaimColumnForMerge($row);
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

                if ($check['result'] > 0){
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



    public function makeISColumnForMerge($row){
        $row = $this->setDefualtColumn($row);
        $row->table = "is";
        $difDate = Carbon::parse($row->hdate)->diffInDays($this->dateFrom);

        $row->difdatefrom2000 = $difDate;
        $row->data_id = $row->id;
        $row->nameSave = $row->name;
        $row->lnameSave = $row->fname;

        $row->name = $row->name;
        $row->lname = $row->fname;
        $row->age = $row->age;
        $row->gender = $row->sex;
        $row->nationality = $row->nationality;
        $row->occupation = $row->occu_t;
        $row->dob = $row->birth;

        if($row->dead == 1 || $row->acc13 == 2 || $row->acc13 == 3 || $row->acc13 == 4 || $row->acc13 == 5 || $row->acc13 == 6  || $row->acc13 == 7 || $row->acc13 == 10){
            $row->is_death = 1;
        }

        $row->name_lenght = strlen($row->NAME);
        $row->is_cid_good = ($row->pid != null and strlen($row->pid) > 10);
        $row->cid_num = intval($row->pid);
        $row->cid = $row->cid_num;
        $row->is_confirm_thai = ctype_digit($row->pid);
        $row->accdate = $row->adate;
        $row->hdate = $row->hdate;
        $row->hospdate = $row->hdate;
        $row->hospcode = $row->hosp;

        $row->vehicle_type = $this->getVehicleType($row);
        if (array_key_exists($row->vehicle_type,$this->vehicleTxt)){
            $row->vehicle_1 = $this->vehicleTxt[$row->vehicle_type];
        }

        return  $row;
    }

    public function make43FileColumnForMerge($row){
        $row = $this->setDefualtColumn($row);
        $row->table = "his";

        $difDate = Carbon::parse($row->DATE_SERV)->diffInDays($this->dateFrom);
        $row->difdatefrom2000 = $difDate;

        $row->data_id = $row->id;
        $row->nameSave = $row->NAME;
        $row->lnameSave = $row->LNAME;

        $row->name = $row->NAME;
        $row->lname = $row->LNAME;
        $row->age = $row->AGE;
        $row->gender = $row->sex;
        $row->nationality = $row->NATION;
        $row->occupation = null;
        $row->is_death = $row->ISDEATH;
        $row->dob = $row->BIRTH;

        $row = $this->cleanNameData($row);
        $row->name_lenght = strlen($row->NAME);
        $row->is_cid_good = ($row->CID != null and strlen($row->CID) > 10);
        $row->cid_num = intval($row->CID);
        $row->cid = $row->cid_num;
        $row->is_confirm_thai = ctype_digit($row->CID);
        $row->accdate = null;
        $row->hdate = $row->DATE_SERV;
        $row->hospdate = $row->DATE_SERV;
        $row->hospcode = $row->HOSPCODE;

        $row->belt_risk = $row->BELT;
        $row->helmet_risk = $row->HELMET;
        $row->alcohol = $row->ALCOHOL;


        $row->vehicle_type = $this->getVehicleType($row);
        if (array_key_exists($row->vehicle_type,$this->vehicleTxt)){
            $row->vehicle_1 = $this->vehicleTxt[$row->vehicle_type];
        }

        return  $row;
    }

    public function makeEclaimColumnForMerge($row){
        $row = $this->setDefualtColumn($row);
        $row->table = "eclaim";

        $difDate = Carbon::parse($row->adate)->diffInDays($this->dateFrom);
        $row->difdatefrom2000 = $difDate;

        $row->data_id = $row->id;
        $row->nameSave = $row->name;
        $row->lnameSave = $row->lname;


        $row->name = $row->name;
        $row->lname = $row->lname;
        $row->nationality = $row->nation;
        $row->age = $row->age;
        $row->dob = $row->birthdate;
        $row->occupation = $row->occupation;
        if ($row->sex == "ชาย"){
            $row->gender = 1;
        }else{
            $row->gender = 2;
        }

        $row = $this->cleanNameData($row);
        $row->name_lenght = strlen($row->name);
        $row->is_cid_good = ($row->cid != null and strlen($row->cid) > 10);
        $row->cid_num = intval($row->cid);
        $row->cid = $row->cid_num;
        $row->is_confirm_thai = ctype_digit($row->cid);
        $row->accdate = $row->adate;

        $row->vehicle_plate_1 = $row->vehicle_plate;
        $row->vehicle_1 = $row->vehicle_typex;


        $row->vehicle_type = $this->getVehicleType($row);
        if (array_key_exists($row->vehicle_type,$this->vehicleTxt)){
            $row->vehicle_1 = $this->vehicleTxt[$row->vehicle_type];
        }

        $row->atumbol = $row->atumbol;
        $row->aaumpor = $row->aaumpor;
        $row->aprovince = $row->aprovince;
        $row->alat = $row->alat;
        $row->along = $row->along;

        $row->hospdate = null;
        $row->hospcode = $row->hospcode;

        return  $row;
    }

    public function makePoliceColumnForMerge($row){
        $difDate = Carbon::parse($row->adate)->diffInDays($this->dateFrom);
        $row = $this->setDefualtColumn($row);
        $row->table = "police";

        $row->difdatefrom2000 = $difDate;

        $row->data_id = $row->id;
        $row->nameSave = $row->name;
        $row->lnameSave = $row->lname;

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
        $row->is_cid_good = ($row->cid != null && strlen($row->cid) > 10 );
        $row->cid_num = intval($row->cid);
        $row->cid = $row->cid_num;
        $row->is_confirm_thai = ctype_digit($row->cid);
        $row->hospdate = null;
        $row->hospcode = null;
        $row->police_event_id = $row->event_id;
        $row->belt_risk = $row->belt;
        $row->helmet_risk = $row->helmet;
        $row->vehicle_plate_1 = $row->vehicle_plate;
        $row->vehicle_1 = $row->vehicle;
        $row->accdate = $row->adate;
        $row->alcohol = $row->alcohol;
        $row->roaduser = $row->roaduser;

        $event = $row->policeEvent;

        $row->atumbol = $event->atumbol;
        $row->aaumpor = $event->aaumpor;
        $row->aprovince = $event->aprovince;
        $row->vehicle_2 = $event->vehicle_2;


        $row->vehicle_type = $this->getVehicleType($row);
        if (array_key_exists($row->vehicle_type,$this->vehicleTxt)){
            $row->vehicle_1 = $this->vehicleTxt[$row->vehicle_type];
        }



        return  $row;
    }

    public function setDefualtColumn($row){

        if (!isset($row->name))  $row->name = null;
        if (!isset($row->lname))  $row->lname = null;
        if (!isset($row->cid))  $row->cid = null;
        if (!isset($row->gender))  $row->gender = null;
        if (!isset($row->nationality))  $row->nationality = null;
        if (!isset($row->dob))  $row->dob = null;
        if (!isset($row->age))  $row->age = null;
        if (!isset($row->is_death))  $row->is_death = null;
        if (!isset($row->occupation))  $row->occupation = null;
        if (!isset($row->alcohol))  $row->alcohol = null;
        if (!isset($row->belt_risk))  $row->belt_risk = null;
        if (!isset($row->helmet_risk))  $row->helmet_risk = null;
        if (!isset($row->roaduser))  $row->roaduser = null;
        if (!isset($row->vehicle_1))  $row->vehicle_1 = null;
        if (!isset($row->vehicle_plate_1))  $row->vehicle_plate_1 = null;
        if (!isset($row->adatetime))  $row->adatetime = null;
        if (!isset($row->atumbol))  $row->atumbol = null;
        if (!isset($row->aaumpor))  $row->aaumpor = null;
        if (!isset($row->aprovince))  $row->aprovince = null;
        if (!isset($row->vehicle_2))  $row->vehicle_2 = null;
        if (!isset($row->police_event_id))  $row->police_event_id = null;
        if (!isset($row->hospcode     ))  $row->hospcode       = null;
        if (!isset($row->alat     ))  $row->alat       = null;
        if (!isset($row->along     ))  $row->along       = null;
        if (!isset($row->hdate     ))  $row->hdate       = null;

        return $row;
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
