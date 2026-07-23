<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotationItemTemplateRequest;
use App\Http\Requests\UpdateQuotationItemTemplateRequest;
use App\Models\QuotationItemTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuotationItemTemplateController extends Controller
{
    /**
     * Display the quotation item templates page.
     */
    public function index()
    {
        return view('dashboard.quotation-item-templates');
    }

    /**
     * Get all quotation item templates for the authenticated user's company.
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = QuotationItemTemplate::where('company_id', $companyId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('item_name', 'asc');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Active filter
        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        $templates = $query->get();

        $data = $templates->map(function ($template) {
            return [
                'id' => $template->id,
                'item_name' => $template->item_name,
                'description' => $template->description,
                'default_quantity' => (float) $template->default_quantity,
                'default_unit_price' => (float) $template->default_unit_price,
                'default_tax_percentage' => (float) $template->default_tax_percentage,
                'sort_order' => $template->sort_order,
                'is_active' => $template->is_active,
                'created_at' => $template->created_at->format('M d, Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Search templates for autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        $search = $request->get('q', '');

        $templates = QuotationItemTemplate::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('item_name', 'asc')
            ->limit(20)
            ->get();

        $data = $templates->map(function ($template) {
            return [
                'id' => $template->id,
                'item_name' => $template->item_name,
                'description' => $template->description,
                'default_quantity' => (float) $template->default_quantity,
                'default_unit_price' => (float) $template->default_unit_price,
                'default_tax_percentage' => (float) $template->default_tax_percentage,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created quotation item template.
     */
    public function store(StoreQuotationItemTemplateRequest $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $template = QuotationItemTemplate::create([
            'company_id' => $companyId,
            'item_name' => $request->item_name,
            'description' => $request->description,
            'default_quantity' => $request->default_quantity ?? 1,
            'default_unit_price' => $request->default_unit_price ?? 0,
            'default_tax_percentage' => $request->default_tax_percentage ?? 0,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quotation item template created successfully.',
            'data' => [
                'id' => $template->id,
                'item_name' => $template->item_name,
                'description' => $template->description,
                'default_quantity' => (float) $template->default_quantity,
                'default_unit_price' => (float) $template->default_unit_price,
                'default_tax_percentage' => (float) $template->default_tax_percentage,
                'sort_order' => $template->sort_order,
                'is_active' => $template->is_active,
            ],
        ], 201);
    }

    /**
     * Display the specified quotation item template.
     */
    public function show(QuotationItemTemplate $quotationItemTemplate): JsonResponse
    {
        $user = Auth::user();

        // Ensure the template belongs to the user's company
        if ($quotationItemTemplate->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $quotationItemTemplate->id,
                'item_name' => $quotationItemTemplate->item_name,
                'description' => $quotationItemTemplate->description,
                'default_quantity' => (float) $quotationItemTemplate->default_quantity,
                'default_unit_price' => (float) $quotationItemTemplate->default_unit_price,
                'default_tax_percentage' => (float) $quotationItemTemplate->default_tax_percentage,
                'sort_order' => $quotationItemTemplate->sort_order,
                'is_active' => $quotationItemTemplate->is_active,
            ],
        ]);
    }

    /**
     * Update the specified quotation item template.
     */
    public function update(UpdateQuotationItemTemplateRequest $request, QuotationItemTemplate $quotationItemTemplate): JsonResponse
    {
        $user = Auth::user();

        // Ensure the template belongs to the user's company
        if ($quotationItemTemplate->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $quotationItemTemplate->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quotation item template updated successfully.',
            'data' => [
                'id' => $quotationItemTemplate->id,
                'item_name' => $quotationItemTemplate->item_name,
                'description' => $quotationItemTemplate->description,
                'default_quantity' => (float) $quotationItemTemplate->default_quantity,
                'default_unit_price' => (float) $quotationItemTemplate->default_unit_price,
                'default_tax_percentage' => (float) $quotationItemTemplate->default_tax_percentage,
                'sort_order' => $quotationItemTemplate->sort_order,
                'is_active' => $quotationItemTemplate->is_active,
            ],
        ]);
    }

    /**
     * Remove the specified quotation item template.
     */
    public function destroy(QuotationItemTemplate $quotationItemTemplate): JsonResponse
    {
        $user = Auth::user();

        // Ensure the template belongs to the user's company
        if ($quotationItemTemplate->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $quotationItemTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quotation item template deleted successfully.',
        ]);
    }
}
