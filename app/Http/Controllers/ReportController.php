<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display a listing of reports.
     */
    public function index()
    {
        $reports = Report::with('member')->get();
        return response()->json($reports);
    }

    /**
     * Store a newly created report in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reports' => 'required|array', // Ensures the input is an array of reports
            'reports.*.memberId' => 'required|exists:members,id', // Each report should have a valid memberId
            'reports.*.date' => 'required|date', // Each report should have a valid date
            'reports.*.workTime' => 'required|integer', // workTime should be an integer
            'reports.*.inTime' => 'required|date_format:Y-m-d H:i:s', // inTime should match the datetime format
            'reports.*.outTime' => 'nullable|date_format:Y-m-d H:i:s', // outTime is optional
            'reports.*.shortLeaveTime' => 'nullable|integer', // shortLeaveTime is optional
            'reports.*.totalWorkTime' => 'nullable|integer', // totalWorkTime is optional
            'reports.*.status' => 'required|integer', // status should be an integer
        ]);

        // Insert all reports at once
        $reports = collect($validated['reports'])->map(function ($reportData) {
            return Report::create($reportData);
        });

        return response()->json($reports, 201);
    }

    /**
     * Display the specified report.
     */
    public function show(Report $report)
    {
        return response()->json($report->load('member'));
    }

    /**
     * Update the specified report in storage.
     */
    public function update(Request $request, Report $report)
    {
        $validated = $request->validate([
            'memberId' => 'sometimes|exists:members,id',
            'date' => 'sometimes|date',
            'workTime' => 'sometimes|integer',
            'inTime' => 'sometimes|date_format:Y-m-d H:i:s',
            'outTime' => 'nullable|date_format:Y-m-d H:i:s',
            'shortLeaveTime' => 'nullable|integer',
            'totalWorkTime' => 'nullable|integer',
            'status' => 'sometimes|integer',
        ]);

        $report->update($validated);
        return response()->json($report);
    }

    /**
     * Remove the specified report from storage.
     */
    public function destroy(Report $report)
    {
        $report->delete();
        return response()->json(['message' => 'Report deleted successfully']);
    }
}
