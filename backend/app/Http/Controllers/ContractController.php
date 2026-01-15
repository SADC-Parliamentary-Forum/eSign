<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index()
    {
        return response()->json(Contract::with('document')->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'reference_number' => 'required|unique:contracts',
            'start_date' => 'required|date',
            'value' => 'required|numeric',
            'counterparty_name' => 'required|string',
        ]);

        $contract = Contract::create($validated);
        return response()->json($contract, 201);
    }
}
