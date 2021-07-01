<?php
namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Models\BuildingRoom  as RoomModel;
use App\Api\Models\Project as ProjectModel;
use App\Api\Models\Building as BuildingModel;
use App\Api\Models\Customer\Customer as CustomerModel;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Building\BuildingService;


/**
 * 项目工位信息
 */
class StationController extends BaseController
{

	public function __construct()
	{
		// Token 验证
		// $this->middleware('jwt.api.auth');
		$this->uid  = auth()->payload()->get('sub');
		if(!$this->uid){
	        return $this->error('用户信息错误');
	    }
	    $this->company_id = getCompanyId($this->uid);
	}

    /**
     * @OA\Post(
     *     path="/api/business/building/station/list",
     *     tags={"房源"},
     *     summary="根据工位ID获取工位信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","proj_id","build_id","orderBy","order"},
     *     ),
     *       example={
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
	public function index(Request $request){
		$pagesize = $request->input('pagesize');
		if(!$pagesize || $pagesize <1){
			$pagesize = config('per_size');
		}
        if($pagesize == '-1'){
            $pagesize = config('export_rows');
        }
		$map= array();

		if ($request->build_id) {
			$map['build_id'] = $request->build_id;
			if ($request->build_floor_id) {
				$map['floor_id'] = $request->floor_id;
			}
		}
		if($request->channel_state){
			$map['channel_state'] = $request->channel_state;
		}
		if($request->room_state){ //1 空闲  0 在租
			$map['room_state'] = $request->room_state;
		}
        if ($request->room_type){ // 1 房间 2 工位
            $map['room_type'] = $request->room_type;
        }else{
            $map['room_type'] = 2;
        }

        // 排序字段
        if($request->input('orderBy')){
            $orderBy = $request->input('orderBy');
        }else{
            $orderBy = 'created_at';
        }
        // 排序方式desc 倒叙 asc 正序
        if($request->input('order')){
            $order = $request->input('order');
        }else{
            $order = 'desc';
        }
		DB::enableQueryLog();
		$result = RoomModel::where($map)
        ->where(function ($q) use($request){
            $request->room_no && $q->where('room_no','like','%'.$request->room_no.'%');
            $request->is_vaild && $q->where('is_vaild',$request->is_vaild);
            $request->station_no && $q->where('station_no','like','%'.$request->station_no.'%');

        })
        ->whereHas('building',function ($q) use ($request){
            $request->proj_ids && $q->whereIn('proj_id',$request->proj_ids);
        })
		->with('building:id,proj_name,build_no,proj_id')
		->with('floor:id,floor_no')
		->orderBy($orderBy,$order)
		->paginate($pagesize)->toArray();

        $data = RoomModel::select(DB::Raw('sum(case room_state when 1 then 1 else 0 end) free_count,count(*) total_count'))
        ->where(function ($q) use($request){
            $request->room_no && $q->where('room_no','like','%'.$request->room_no.'%');
            $request->is_vaild && $q->where('is_vaild',$request->is_vaild);
            $request->station_no && $q->where('station_no','like','%'.$request->station_no.'%');
        })
        ->whereHas('building',function ($q) use ($request){
            $request->proj_ids && $q->whereIn('proj_id',$request->proj_ids);
        })
        ->where($map)
        ->first();
		// return response()->json(DB::getQueryLog());
        $result = $this->handleBackData($result);

        $contract = new ContractService;
        $buildService  = new BuildingService;
        if ($data['free_count']) {
            $freeRate = numFormat($data['free_count'] / $data['total_count'] *100);
        }else{
            $freeRate = '0.00';
            $data['free_count'] = 0;
        }
        if ($result['result']) {
            $result['result'] = $buildService->formatData($result['result']);
        }

        $avgPrice = $contract->contractAvgPrice(2); // 工位 room_type = 2
        Log::error($avgPrice);
        $stat = array(  ['title'=>'空闲工位','value'=>$data['free_count']],
                        ['title'=>'总工位','value'=>$data['total_count']],
                        ['title'=>'空闲率','value'=>$freeRate.'%'],
                        ['title'=>'在租平均单价','value'=>$avgPrice.'元/天']);
        $result['stat'] = $stat;
       	return $this->success($result);
	}

	/**
     * @OA\Post(
     *     path="/api/business/building/station/add",
     *     tags={"房源"},
     *     summary="新增工位",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"floor_id","build_id","room_no","station_no"},
     *       @OA\Property(
     *          property="floor_id",
     *          type="int",
     *          description="楼层ID"
     *       ),
     *       @OA\Property(
     *          property="build_id",
     *          type="int",
     *          description="楼号Id"
     *       ),
     *       @OA\Property(
     *          property="room_no",
     *          type="String",
     *          description="房间号"
     *       ),
     *       @OA\Property(
     *          property="station_list",
     *          type="list",
     *          description="工位号列表"
     *       )
     *     ),
     *       example={
     *              "build_id":1,"floor_id":"","room_no":"","station_list":"[]"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
	public function store(Request $request){
		$validatedData = $request->validate([
            // 'proj_id' =>'required|min:1',
            'build_id' => 'required|numeric|gt:0',
            'floor_id' => 'required|numeric|gt:0',
            'room_no' => 'required|String|min:1',
            'station_list' => 'required|array',
        ]);

        $building = BuildingModel::whereId($request->build_id)->exists();
        if (!$building) {
        	return $this->error('楼宇不存在');
        }

        $data = $request->toArray();
        $fail_msg = "";
        foreach ($data['station_list'] as $k => $v) {
            $data['station_no'] = $v['station_no'];
            $res = $this->saveStation($data);
            if(!$res){
               $fail_msg .= $v."|";
            }
        }
        if($fail_msg){
            $fail_msg .= '工位编号重复';
        }
        return $this->success('工位保存成功.'.$fail_msg);

	}


    /**
     * @OA\Post(
     *     path="/api/business/building/station/edit",
     *     tags={"房源"},
     *     summary="工位编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","floor_id","build_id","room_no","station_no"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="Id"
     *       ),
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目Id"
     *       ),
     *       @OA\Property(
     *          property="build_id",
     *          type="int",
     *          description="楼号Id"
     *       ),
     *       @OA\Property(
     *          property="room_no",
     *          type="String",
     *          description="房间号"
     *       )
     *     ),
     *       example={
     *              "id":1,"proj_id": 11,"build_id":1,"room_no":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
    public function update(Request $request){
        $validatedData = $request->validate([
            'id' =>'required|numeric|gt:0',
            'build_id' => 'required|numeric|gt:0',
            'floor_id' => 'required|numeric|gt:0',
            'room_type' => 'required|numeric|in:1,2',
            'room_no' => 'required|String',
            'station_list' => 'required|array',
        ]);


        $data = $request->toArray();
        $res = $this->saveStation($data,2);

        if($res){
            return $this->success('工位保存成功。');
        }
        return $this->error('工位保存失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/station/enable",
     *     tags={"房源"},
     *     summary="工位启用禁用",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","is_vaild"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="房源ID集合"
     *       ),
     *       @OA\Property(
     *          property="is_vaild",
     *          type="int",
     *          description="0禁用1 启用"
     *       )
     *
     *     ),
     *       example={
     *              "Ids":"[1]","is_vaild":0
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
    public function enable(Request $request){
        $validatedData = $request->validate([
            'Ids' =>'required|array',
            'is_vaild' => 'required|numeric|in:0,1',
        ]);
        $data['is_vaild'] = $request->is_vaild;
        $res = RoomModel::whereIn('id',$request->Ids)->update($data);
        if($res){
            return $this->success('工位禁用启用成功。');
        }
        return $this->error('工位禁用启用失败！');
    }

    private function saveStation($DA,$type=1){
        $res = $this->checkReapet($DA,$type);
        if ($res) {
            return 0;
        }
        if ($type == 1) {
            $station = new RoomModel;
            $station->c_uid = $this->uid;
            $station->company_id = $this->company_id;
            $station->room_area = 1;   // 工位面积默认为1 不显示，合同处理单价的时候做处理
        }else{
            $station = RoomModel::find($DA['id']);
            $station->u_uid = $this->uid;
        }

        $station->build_id = $DA['build_id'];
        $station->floor_id = $DA['floor_id'];
        $station->room_no = $DA['room_no'];
        $station->room_state = $DA['room_state'];
        $station->room_type = 2;   // 工位2 房源1
        $station->station_no = isset($DA['station_no']) ? $DA['station_no'] :0;
        $station->room_measure_area = isset($DA['room_measure_area']) ?$DA['room_measure_area']:0;
        $station->room_trim_state = isset($DA['room_trim_state']) ?$DA['room_trim_state'] :"";
        $station->room_price = isset($DA['room_price']) ? $DA['room_price']:0.00;
        $station->room_tags = isset($DA['room_tags'])?$DA['room_tags']:"";
        $station->channel_state = isset($DA['channel_state']) ? $DA['channel_state'] :0;
        Log::error($DA['rentable_date']."000000");
        if(isset($DA['rentable_date']) && isDate($DA['rentable_date'])){
            $station->rentable_date = $DA['rentable_date'];
        }
        $station->remark = isset($DA['remark']) ? $DA['remark'] :"";
        $res = $station->save();
        return $res;
    }

    private function checkReapet($DA,$type = 1){
        $map['build_id']    = $DA['build_id'];
        $map['room_no']     = $DA['room_no'];
        $map['station_no']  = $DA['station_no'];
        $map['floor_id']    = $DA['floor_id'];
        if ($type != 1) {
            $res = RoomModel::where($map)->where('id','!=',$DA['id'])->exists();
        }else{
            $res = RoomModel::where($map)->exists();
        }

        return $res;
    }
}