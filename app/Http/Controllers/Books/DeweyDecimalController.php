<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use App\Models\DeweyDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeweyDecimalController extends Controller
{
    public function callnumberRegister(Request $request)
    {
        $validatedData = $request->validate([
            'dewey_number' => ['required', 'string', 'max:20', 'unique:dewey_decimals,dewey_number', 'regex:/^\d{3}(\.\d+)?$/'],
            'class_name'   => 'nullable|string|max:255',
            'description'  => 'nullable|string',
        ]);

        $deweyDecimal = DeweyDecimal::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Dewey decimal registered successfully.',
            'data'    => $deweyDecimal,
        ], 201);
    }

    public function batchImport(Request $request)
    {
        $validated = $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.dewey_number' => ['required', 'string', 'regex:/^\d{3}(\.\d+)?$/'],
            'items.*.class_name'   => 'nullable|string|max:255',
            'items.*.description'  => 'nullable|string',
        ]);

        $records = [];
        $now = now();

        foreach ($validated['items'] as $item) {
            $records[] = [
                'dewey_number' => $item['dewey_number'],
                'class_name'   => $item['class_name'] ?? null,
                'description'  => $item['description'] ?? null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        DeweyDecimal::upsert(
            $records,
            ['dewey_number'],
            ['class_name', 'description', 'updated_at']
        );

        return response()->json([
            'success' => true,
            'message' => count($records) . ' Dewey decimal records imported successfully.',
        ], 200);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Querying your exact database columns
        $results = DeweyDecimal::query()
            ->select(['id', 'dewey_number', 'class_name', 'description'])
            ->where('dewey_number', 'LIKE', "{$query}%")
            ->orWhere('class_name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get();

        return response()->json($results);
    }
}
