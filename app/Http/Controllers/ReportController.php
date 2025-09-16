<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Report;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
        var_dump($request->all());
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
                    'key' => $date,
                    'id' => $report->id,
                    'date' => $date,
                    'dayName' => $dayName,
                    'workTime' => $report->workTime,
                    'inTime' => $report->inTime,
                    'outTime' => $report->outTime,
                    'shortLeaveTime' => $report->shortLeaveTime,
                    'totalWorkTime' => $report->totalWorkTime,
                    'status' => $report->status,
                ];

                // Summing up work time for the summaryk
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
                    'key' => $date,
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
            'member' => $member,
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
        // Fetch member details with related reports (if relation exists)
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Fetch reports for that year
        $reports = Report::where('memberId', $memberId)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(function ($report) {
                return Carbon::parse($report->date)->format('F');
            });

        if ($reports->isEmpty()) {
            return response()->json(['message' => 'No reports found for this member in the specified year'], 404);
        }

        // Monthly summary

        $monthlySummary = $reports->map(function ($monthReports, $month) use ($year) {

            // print_r($monthReports);
            $monthNumber = Carbon::parse("$month 1 $year")->month;
            $totalDaysInMonth = Carbon::create($year, $monthNumber)->daysInMonth;

            $totalPresentDays = $monthReports->count();
            $leaveDays = $totalDaysInMonth - $totalPresentDays;

            $avgInTime = $monthReports->avg(fn ($r) => is_numeric($r->inTime) ? $r->inTime : strtotime($r->inTime));
            $avgOutTime = $monthReports->avg(fn ($r) => is_numeric($r->outTime) ? $r->outTime : strtotime($r->outTime));

            return [
                'month'             => $month,
                'totalWorkComplete' => $monthReports->sum('totalWorkTime'),
                'averageWorkTime'   => round($monthReports->avg('totalWorkTime'), 2),
                'totalWorkTimeSum'  => $monthReports->sum('workTime'),
                'averageInTime'     => $avgInTime ? Carbon::createFromTimestamp(round($avgInTime))->format('H:i:s') : null,
                'averageOutTime'    => $avgOutTime ? Carbon::createFromTimestamp(round($avgOutTime))->format('H:i:s') : null,
                'totalPresentDays'  => $totalPresentDays,
                'leaveDays'         => $leaveDays,
            ];
        })->values();

        // Flatten all reports for yearly summary
        $allReports = $reports->flatten();

        $yearlySummary = [
            'year'              => $year,
            'totalWorkComplete' => $allReports->sum('totalWorkTime'),
            'averageWorkTime'   => round($allReports->avg('totalWorkTime'), 2),
            'totalWorkTimeSum'  => $allReports->sum('workTime'),
            'totalPresentDays'  => $allReports->count(),
            'totalLeaveDays'    => $monthlySummary->sum('leaveDays'),
        ];

        return response()->json([
            'member' => $member->only(['id', 'name', 'email', 'avatar', 'joinDate', 'endDate', 'workTime', 'status','leave']),
            'yearSummary' => $yearlySummary,
            'monthlySummary' => $monthlySummary
        ]);
    }

    public function getMemberAttendanceReport($memberId, $year = null)
    {
        $year = $year ?? Carbon::now()->year;
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Start of year or member join date
        $startDate = Carbon::parse($member->joinDate)->max(Carbon::create($year, 1, 1));

        // Last report date for the member in the year
        $lastReportDate = Report::where('memberId', $memberId)
            ->whereYear('date', $year)
            ->max('date');

        if ($lastReportDate) {
            $endDate = Carbon::parse($lastReportDate);
        } else {
            $endDate = $startDate; // No reports, end date = start date
        }

        // Fetch all reports for member in the year up to last report date
        $allReports = Report::where('memberId', $memberId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Group by month
        $monthlyReports = $allReports->groupBy(fn ($r) => Carbon::parse($r->date)->format('F'));

        $monthlySummary = $monthlyReports->map(function ($monthReports, $month) use ($year) {
            $monthNumber = Carbon::parse("$month 1 $year")->month;
            $daysInMonth = Carbon::create($year, $monthNumber)->daysInMonth;
            $totalPresent = $monthReports->count();
            $leaveDays = $daysInMonth - $totalPresent;

            return [
                'month'             => $month,
                'totalWorkComplete' => $monthReports->sum('totalWorkTime'),
                'averageWorkTime'   => round($monthReports->avg('totalWorkTime'), 2),
                'totalWorkTimeSum'  => $monthReports->sum('workTime'),
                'totalPresentDays'  => $totalPresent,
                'leaveDays'         => $leaveDays,
            ];
        })->values();

        // Yearly summary based on last report date
        $totalPresentDays = $allReports->count();
        $totalPlanned = $allReports->sum('workTime');
        $totalActual = $allReports->sum('totalWorkTime');
        $timeDiff = $totalActual - $totalPlanned;
        $dailyWorkTime = $member->workTime > 0 ? $member->workTime : 1;

        $totalDays = $startDate->diffInDays($endDate) + 1;

        $yearlySummary = [
            'year'              => $year,
            'totalWorkComplete' => $totalActual,
            'averageWorkTime'   => round($allReports->avg('totalWorkTime'), 2),
            'totalWorkTimeSum'  => $totalPlanned,
            'totalPresentDays'  => $totalPresentDays,
            'totalLeaveDays'    => $totalDays - $totalPresentDays,
            'annualLeaveDays'   => (int) $member->leave ?? 0,
            'timeDifference'    => $timeDiff,
            'timeDifferenceDay' => round($timeDiff / $dailyWorkTime, 2),
            'startDate'         => $startDate->toDateString(),
            'endDate'           => $endDate->toDateString(),
        ];

        return response()->json([
            'member'         => $member->only(['id', 'name', 'email', 'avatar', 'joinDate', 'endDate', 'workTime', 'status']),
            'monthlySummary' => $monthlySummary,
            'yearSummary'    => $yearlySummary,
        ]);
    }

    public function getYearlyAttendanceReport($year = null)
    {
        $year = $year ?? Carbon::now()->year;
        $startOfYear = Carbon::create($year, 1, 1);
        $endOfYear = Carbon::create($year, 12, 31);

        $members = Member::where('status', 1)->get();
        $report = [];

        foreach ($members as $member) {
            // Member start date
            $startDate = Carbon::parse($member->joinDate);
            if ($startDate->lt($startOfYear)) {
                $startDate = $startOfYear;
            }

            // Member last updated attendance
            $lastAttendance = Report::where('memberId', $member->id)
                ->whereYear('date', $year)
                ->orderBy('updated_at', 'desc')
                ->first();
            $endDate = $lastAttendance ? Carbon::parse($lastAttendance->updated_at) : $endOfYear;
            if ($endDate->gt($endOfYear)) {
                $endDate = $endOfYear;
            }

            // Generate all dates in the range
            $allDates = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $allDates[] = $date->toDateString();
            }

            // Attendance for member
            $attendance = Report::where('memberId', $member->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $presentDates = $attendance->pluck('date')->toArray();

            // Member annual leave dates from members.leave JSON column


            // Count present days
            $presentDays = count($presentDates);

            // Leave days = total dates - present dates - approved annual leave
            $leaveDays = count($allDates) - $presentDays;
            // Sum of planned and actual work times
            $totalPlanned = $attendance->sum('workTime');
            $totalActual = $attendance->sum('totalWorkTime');
            $timeDifference = $totalActual - $totalPlanned;

            // Time difference in days using member's daily workTime
            $dailyWorkTime = $member->workTime > 0 ? $member->workTime : 1;
            $timeDifferenceDays = round($timeDifference / $dailyWorkTime, 2);
            $report[] = [
                'memberId'             => $member->id,
                'avatar'             => $member->avatar,
                'name'                 => $member->name,
                'email'                => $member->email,
                'joinDate'             => $member->joinDate,
                'startDate'            => $startDate->toDateString(),
                'endDate'              => $endDate->toDateString(),
                'presentDays'          => $presentDays,
                'leaveDays'            => $leaveDays,
                'annualLeaveDays'      => (int) $member->leave ?? 0,
                'totalDays'            => count($allDates),
                'totalWorkTime'        => $totalPlanned,
                'totalActual'          => $totalActual,
                'timeDifference'       => $timeDifference,
                'timeDifferenceDay'    => $timeDifferenceDays,
            ];
        }
        return response()->json($report);
    }


}
