<?php

namespace App\Http\Controllers;

use App\Models\Member;
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
        // Validate the request data
        $validated = $request->validate([
            'data' => 'required|array', // The data key should be an array
            'data.*.memberId' => 'required|exists:members,id', // Ensure each item has a valid memberId
            'data.*.date' => 'required|date', // Ensure each item has a valid date
            'data.*.workTime' => 'required|integer', // Ensure each item has a valid workTime
            'data.*.inTime' => 'required|date_format:Y-m-d H:i:s', // Ensure each item has a valid inTime
            'data.*.outTime' => 'nullable|date_format:Y-m-d H:i:s', // Optional outTime
            'data.*.shortLeaveTime' => 'nullable|integer', // Optional shortLeaveTime
            'data.*.totalWorkTime' => 'nullable|integer', // Optional totalWorkTime
        ]);

        // Insert all reports at once
        $reports = collect($validated['data'])->map(function ($reportData) {
            return Report::create($reportData);
        });

        // Return the created reports
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

    public function getReportsByMember($memberId)
    {
        $reports = Report::where('memberId', $memberId)->with('member')->get();

        if ($reports->isEmpty()) {
            return response()->json(['message' => 'No reports found for this member'], 404);
        }

        return response()->json($reports);
    }

    public function getReportsByMemberAndMonth($memberId, $year, $monthName)
    {
        // Validate year format
        if (!preg_match('/^\d{4}$/', $year)) {
            return response()->json(['error' => 'Invalid year format. Use YYYY (e.g., 2024).'], 400);
        }

        // Validate month name and convert it to numeric month

        $month = \Carbon\Carbon::createFromFormat('d F Y', "01 $monthName $year")->format('m');

        if (!$month) {
            return response()->json(['error' => 'Invalid month name.'], 400);
        }

        // Fetch reports for the given member, month, and year
        $reports = Report::where('memberId', $memberId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->keyBy('date'); // Key by date for easy lookup

        // Get member details
        $member = Member::find($memberId);
        if (!$member) {
            return response()->json(['error' => 'Member not found.'], 404);
        }

        // Get total days in the month


        $daysInMonth =  \Carbon\Carbon::create($year, $month, 1)->daysInMonth;


        // Generate a complete list of dates in the month
        $fullMonthReports = [];
        $totalWorkTime = 0;
        $totalPresentDays = 0;
        $totalWorkHours = 0;
        $inTimes = [];
        $outTimes = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d-%02d', $year, $month, $day);
            $dayName = \Carbon\Carbon::parse($date)->format('l'); // Get day name (Monday, Tuesday, etc.)

            if (isset($reports[$date])) {
                // If report exists for this date
                $report = $reports[$date];
                $fullMonthReports[] = [
                    'date' => $date,
                    'dayName' => $dayName,
                    'workTime' => $report->workTime,
                    'inTime' => $report->inTime,
                    'outTime' => $report->outTime,
                    'shortLeaveTime' => $report->shortLeaveTime,
                    'totalWorkTime' => $report->totalWorkTime,
                    'status' => $report->status,
                ];

                // Summing up work time for the summary
                $totalWorkTime += $report->totalWorkTime;
                $totalPresentDays++;
                $totalWorkHours += $report->workTime;

                // Collect in-time and out-time for averages
                if ($report->inTime) {
                    $inTimes[] = strtotime($report->inTime);
                }
                if ($report->outTime) {
                    $outTimes[] = strtotime($report->outTime);
                }
            } else {
                // If no report found, mark as leave
                $fullMonthReports[] = [
                    'date' => $date,
                    'dayName' => $dayName,
                    'workTime' => 0,
                    'inTime' => null,
                    'outTime' => null,
                    'shortLeaveTime' => 0,
                    'totalWorkTime' => 0,
                    'status' => 0, // Absent/Leave
                ];
            }
        }

        // Calculate leave days
        $leaveDays = $daysInMonth - $totalPresentDays;

        // Calculate average in-time and out-time
        $averageInTime = !empty($inTimes) ? date('H:i:s', array_sum($inTimes) / count($inTimes)) : null;
        $averageOutTime = !empty($outTimes) ? date('H:i:s', array_sum($outTimes) / count($outTimes)) : null;

        // Prepare response
        return response()->json([
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar,
                'joinDate' => $member->joinDate,
                'status' => $member->status,
            ],
            'monthSummary' => [
                'month' => $monthName, // Use full month name
                'year' => $year,
                'totalWorkComplete' => $totalWorkTime,
                'averageWorkTime' => $totalPresentDays > 0 ? $totalWorkTime / $totalPresentDays : 0,
                'totalPresentDays' => $totalPresentDays,
                'leaveDays' => $leaveDays,
                'totalWorkTimeSum' => $totalWorkHours,
                'averageInTime' => $averageInTime,
                'averageOutTime' => $averageOutTime,
            ],
            'reports' => $fullMonthReports, // Full list of reports for the month
        ]);
    }



    public function getReportsByMemberAndYear($memberId, $year)
    {
        // Fetch member details
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $reports = Report::where('memberId', $memberId)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(function ($report) {
                return \Carbon\Carbon::parse($report->date)->format('F'); // Group by month name
            });

        if ($reports->isEmpty()) {
            return response()->json(['message' => 'No reports found for this member in the specified year'], 404);
        }

        // Monthly summary (without individual reports)
        $monthlySummary = $reports->map(function ($monthReports, $month) use ($year) {
            $totalDaysInMonth = \Carbon\Carbon::parse("$year-$month-01")->daysInMonth;
            $totalPresentDays = $monthReports->count();
            $leaveDays = $totalDaysInMonth - $totalPresentDays;

            return [
                'month' => $month,
                'totalWorkComplete' => $monthReports->sum('totalWorkTime'),
                'averageWorkTime' => round($monthReports->avg('totalWorkTime'), 2),
                'totalWorkTimeSum' => $monthReports->sum('workTime'),
              'averageInTime' => $monthReports->avg(function ($report) {
                  return is_numeric($report->inTime) ? $report->inTime : strtotime($report->inTime);
              }) ? \Carbon\Carbon::createFromTimestamp(round($monthReports->avg(function ($report) {
                  return is_numeric($report->inTime) ? $report->inTime : strtotime($report->inTime);
              })))->format('H:i:s') : null,

'averageOutTime' => $monthReports->avg(function ($report) {
    return is_numeric($report->outTime) ? $report->outTime : strtotime($report->outTime);
}) ? \Carbon\Carbon::createFromTimestamp(round($monthReports->avg(function ($report) {
    return is_numeric($report->outTime) ? $report->outTime : strtotime($report->outTime);
})))->format('H:i:s') : null,
                'totalPresentDays' => $totalPresentDays,
                'leaveDays' => $leaveDays
            ];
        })->values(); // Convert to array

        // Yearly summary
        $yearlySummary = [
            'year' => $year,
            'totalWorkComplete' => $reports->flatten()->sum('totalWorkTime'),
            'averageWorkTime' => round($reports->flatten()->avg('totalWorkTime'), 2),
            'totalWorkTimeSum' => $reports->flatten()->sum('workTime'),
            'totalPresentDays' => $reports->flatten()->count(),
            'totalLeaveDays' => collect($monthlySummary)->sum('leaveDays')
        ];

        return response()->json([
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar,
                'joinDate' => $member->joinDate,
                'endDate' => $member->endDate,
                'workTime' => $member->workTime,
                'status' => $member->status,
            ],
            'yearSummary' => $yearlySummary,
            'monthlySummary' => $monthlySummary
        ]);
    }





}
