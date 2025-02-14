<?php

namespace App\Http\Controllers;

use App\Models\ManufacturingMaterials;
use App\Models\MaterialPurchased;
use App\Models\MPRecord;
use App\Models\RequestQuotationSuppliers;
use Illuminate\Http\Request;

use App\Models\Supplier;
use App\Models\SupplierGroup;
use App\Models\SuppliersQuotation;
use Exception;
use Illuminate\Support\Facades\DB;


class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $suppliers = Supplier::withCount('sg_materials')->get(['company_name', 'contact_name', 'phone_number', 'supplier_address']);
        $materials = ManufacturingMaterials::all(['item_code', 'item_name']);
        return view('modules.buying.supplier', ['suppliers' => $suppliers, 'materials' => $materials]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('modules.buying.createnewsupplier');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        try {
            $data = new Supplier();

            $form_data = $request->input();

            $lastSupplier = Supplier::orderby('created_at', 'desc')->first();
            $nextId = ($lastSupplier)
                ? Supplier::orderby('created_at', 'desc')->first()->id + 1 :
                1;

            $data->supplier_id = "SUP" . $nextId;

            $data->company_name = $form_data['supplier_name'];
            //$data->supplier_group = $form_data['supplier_group'];
            if (isset($form_data['supplier_contact'])) {
                $data->contact_name = $form_data['supplier_contact'];
            }
            $data->phone_number = $form_data['supplier_phone'];
            $data->supplier_email = $form_data['supplier_email'];
            $data->supplier_address = $form_data['supplier_address'];

            $data->save();
        } catch (Exception $e) {
            return $e;
        }
    }

    public function getSupplier($supplier_id)
    {
        return ['supplier' => Supplier::where('supplier_id', $supplier_id)->first()];
    }

    public function getSupplierData()
    {
        return response()
            ->json([
                'suppliers' => Supplier::withCount('sg_materials')
                    ->get(
                        ['company_name', 'contact_name', 'phone_number', 'supplier_address']
                    )
            ]);
    }

    public function filterByID($supplier_id)
    {
        $suppliers = Supplier::where('supplier_id', $supplier_id)
                     ->withCount('sg_materials')->get();
        return response()->json(['suppliers' => $suppliers]);
    }

    public function filterBySupplierGroup($item_code)
    {
        $suppliers = DB::table('suppliers')
            ->join('supplier_group', 'supplier_group.supplier_id', '=', 'suppliers.supplier_id')
            ->where('supplier_group.item_code', '=', $item_code)
            ->get();
        foreach ($suppliers as $supplier) {
            # code...
            $rms = Supplier::find($supplier->id)->first()->sg_materials;
            $supplier->rm_count = $rms->count();
        }
        return response()->json(['suppliers' => $suppliers]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        //DB::connection()->enableQueryLog();
        $supplier = Supplier::find($id);
        $counts = array();
        $counts['sq_count'] = SuppliersQuotation::where('supplier_id', $supplier->supplier_id)->count();
        $counts['rq_count'] = RequestQuotationSuppliers::where('supplier_id', $supplier->supplier_id)->count();
        $mp = MaterialPurchased::where('items_list_purchased', 'LIKE', "%" . $supplier->supplier_id . "%")
                                ->whereNotIn('mp_status', ['Draft', 'Cancelled'])
                                ->withCount('receipt')
                                ->get();
        $counts['po_count'] = $mp->count();
        $pr_count = 0;
        $pi_count = 0;
        foreach ($mp as $record) {
            if ($record->receipt_count > 0) {
                $pr_count++;
                $pr = $record->receipt->invoice;
                if ($pr->invoice !== null) $pi_count++;
            }
        }
        $counts['pr_count'] = $pr_count;
        $counts['pi_count'] = $pi_count;

        $supplier_mats = $supplier->sg_materials;
        foreach ($supplier_mats as $rm) {
            # code...
            $mp_record = MPRecord::where('item_code', $rm->item_code)
                        ->where('supplier_id', $rm->supplier_id)
                        ->with('material')
                        ->first();
            $rm->rm_rate = is_null($mp_record) ? 'N/A' : $mp_record->rate;
            $rm->item = is_null($mp_record) ? 
                        ManufacturingMaterials::where('item_code', $rm->item_code)->first() :
                        $mp_record->material;
        }

        //echo dd(DB::getQueryLog());

        return view('modules.buying.supplierInfo', ['supplier' => $supplier, 'counts' => $counts, 'material_data' => $supplier_mats]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        try {
            $data = Supplier::find($id);

            $form_data = $request->input();

            $data->company_name = $form_data['supplier_name'];
            $data->supplier_group = $form_data['supplier_group'];
            if (isset($form_data['supplier_contact'])) {
                $data->contact_name = $form_data['supplier_contact'];
            }
            $data->contact_name = $form_data['supplier_contact'];
            $data->phone_number = $form_data['supplier_phone'];
            $data->supplier_email = $form_data['supplier_email'];
            $data->supplier_address = $form_data['supplier_address'];

            $data->save();
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $supplier = Supplier::find($id);
        $supplier->delete();
    }
}
