<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
     // 📌 GET ALL
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => SubscriptionPlan::latest()->get()
        ]);
    }  
    // 📌 STORE
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'features' => 'nullable|array'
        ]);

        $plan = SubscriptionPlan::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Plan créé',
            'data' => $plan
        ], 201);
    }

    // 📌 SHOW
    public function show($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }

    // 📌 UPDATE
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé'
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'features' => 'nullable|array'
        ]);

        $plan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Plan mis à jour',
            'data' => $plan
        ]);
    }

    // 📌 DELETE
    public function destroy($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé'
            ], 404);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan supprimé'
        ]);
    }
}
