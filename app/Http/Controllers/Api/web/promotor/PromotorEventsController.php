<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Http\Traits\PaginatesRequests;
use App\Models\Barman;
use App\Models\BarStore;
use App\Models\Category;
use App\Models\City;
use App\Models\Event;
use App\Models\Invite;
use App\Models\LineUp;
use App\Models\Products;
use App\Models\Protocol;
use App\Models\Province;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TypeEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PromotorEventsController extends Controller
{
    use AuthorizesEventAccess, PaginatesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $baseQuery = Event::query();

        if (Auth::user()->role_id != 1) {
            $baseQuery->where('user_id', Auth::user()->id);
        }

        $events = (clone $baseQuery)
            ->when($request->query('query'), function ($builder, $search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status_id'), function ($builder, $statusId) {
                $builder->where('status_id', $statusId);
            })
            ->when($request->query('province_id'), function ($builder, $provinceId) {
                $builder->where('province_id', $provinceId);
            })
            ->with(['city', 'province', 'category', 'status', 'type'])
            ->withCount('sell_details as tickets_sold')
            ->withSum(['sells as revenue' => function ($query) {
                $query->where('status', 1);
            }], 'total')
            ->orderByDesc('id')
            ->paginate($this->perPageTable($request))
            ->appends($request->query());

        return response()->json([
            'event' => $events,
            'provinces' => Province::orderBy('name')->get(['id', 'name']),
            'statuses' => Status::orderBy('id')->get(['id', 'name']),
            'summary' => $this->summary($baseQuery),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $province = Province::get();
        $city = City::get();
        $categories = Category::get();
        $typeevent = TypeEvent::get();
        
        return response()->json([
            "province" => $province,
            "city" => $city,
            "category" => $categories,
            "typeevent"=>$typeevent,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data = $request->all();

        $imagePath = '';
        if ($request->hasFile('image')) {
            $imageName = time().'.'.$request->file('image')->extension();
            $request->file('image')->storeAs('public/event', $imageName);
            $imagePath = 'event/'.$imageName;
        }

        $event = Event::create([
            'user_id'=> Auth::user()->id,
            'name' => $data['name'],
            'image' => $imagePath,
            'province_id' => $data['province_id'],
            'city_id' => $data['city_id'],
            'description' => $data['description'],
            'main_category_id' => $data['main_category_id'],
            'second_category_id' => $data['second_category_id'],
            'address' => $data['address'],
            'start_date' => date('Y-m-d',strtotime($data['start_date'])),
            'start_time' => $data['start_time'],
            'end_date' => date('Y-m-d',strtotime($data['end_date'])),
            'end_time' => $data['end_time'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'website' => $data['website'],
            'instagram' => $data['instagram'],
            'facebook' => $data['facebook'],
            'twitter' => $data['twitter'],
            'youtube' => $data['youtube'],
            'status_id' => 3,
            'type_event_id' => $data['type_event_id'],
            'tax' => 10,
        ]);
        return response()->json($event);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::with('city')->with('province')->with('category')->with('status')->with('type')
            ->with('user:id,name,email,mobile')->find($id);
        $province = Province::get();
        $city = City::get();
        $categories = Category::get();
        $typeevent = TypeEvent::get();
        $tickets = Ticket::where('event_id',$id)->where('is_package',0)->orderBy('id','desc')->get();
        $packages = Ticket::where('event_id',$id)->where('is_package',1)->orderBy('id','desc')->get();
        $barstores = BarStore::where('event_id',$id)->with('products')->orderBy('id','desc')->get();
        $lineups = LineUp::where('event_id',$id)->orderBy('id','desc')->get();
        $product = Products::where('event_id',$id)->with('barstore')->orderBy('name','asc')->get();
        $protocols = Protocol::where('event_id',$id)->withCount('tickets')->orderBy('name','asc')->get();
        $barmans = Barman::where('event_id',$id)->with('barstore')->orderBy('name','asc')->get();
        $invites = Invite::where('event_id',$id)->orderBy('name','asc')->get();



        return response()->json([
            "event"=>$event,
            "province" => $province,
            "city" => $city,
            "category" => $categories,
            "typeevent"=>$typeevent,
            "tickets"=>$tickets,
            "bar"=>$barstores,
            "package"=>$packages,
            "lineup"=>$lineups,
            "products"=>$product,
            "protocols"=>$protocols,
            "barmans"=>$barmans,
            'invites'=>$invites,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::with('city')->with('province')->with('category')->with('status')->with('type')->find($id);
        $province = Province::get();
        $city = City::get();
        $categories = Category::get();
        $typeevent = TypeEvent::get();
        $status = Status::get();
        return response()->json([
            "event"=>$event,
            "province" => $province,
            "city" => $city,
            "category" => $categories,
            "typeevent"=>$typeevent,
            "status"=>$status
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::find($id);
        $data = $request->all();

        $data['start_date'] = date('Y-m-d',strtotime($data['start_date']));
        $data['end_date'] = date('Y-m-d',strtotime($data['end_date']));

        if ($request->hasFile('image')) {
            $imageName = time().'.'.$request->file('image')->extension();
            $request->file('image')->storeAs('public/event', $imageName);
            $data['image'] = 'event/'.$imageName;
        }

        $event->update($data);

        return $request->all();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (!in_array((int) $event->status_id, [3, 4], true)) {
            return response()->json([
                'message' => 'Só podes eliminar eventos pendentes ou em revisão.',
            ], 422);
        }

        $event->delete();

        return response()->json(['message' => 'Evento eliminado.']);
    }

    public function auxiliar($id)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $barstore = BarStore::where('event_id', $id)->get();

        return response()->json([
            'barstore' => $barstore,
        ]);
    }

    private function summary($baseQuery): array
    {
        $counts = (clone $baseQuery)
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        return [
            'total' => (int) $counts->sum(),
            'approved' => (int) ($counts[2] ?? 0),
            'pending' => (int) ($counts[3] ?? 0),
            'review' => (int) ($counts[4] ?? 0),
            'canceled' => (int) ($counts[1] ?? 0),
        ];
    }
}
