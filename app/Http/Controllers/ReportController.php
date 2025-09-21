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
        return response()->json(['message' => 'data create successfully',"report" => $reports], 201);
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

        $res = $report->update($validated);
        if ($res) {

            return response()->json(['message' => 'data update'], 200);
        }
        return response()->json(['message' => 'data not updated'], 400);
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

        // Get member
        $member = Member::find($memberId);
        if (!$member) {
            return response()->json(['error' => 'Member not found.'], 404);
        }

        // Convert month name to numeric month safely
        try {
            $month = \Carbon\Carbon::createFromFormat('F Y', "$monthName $year")->month;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid month name.'], 400);
        }

        // Member's join date
        $memberJoin = Carbon::parse($member->joinDate);

        // Start and end of the month
        $monthStart = Carbon::create($year, $month, 1)->max($memberJoin); // start cannot be before join
        $monthEnd   = Carbon::create($year, $month, 1)->endOfMonth();

        // Fetch reports for this member and month
        $reports = Report::where('memberId', $memberId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get()
            ->keyBy('date');

        $daysInMonth = $monthStart->diffInDays($monthEnd) + 1;

        $fullMonthReports = [];
        $totalWorkTime = 0;
        $totalPresentDays = 0;
        $totalWorkHours = 0;
        $inTimes = [];
        $outTimes = [];

        for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
            $dateStr = $date->toDateString();
            $dayName = $date->format('l');

            if (isset($reports[$dateStr])) {
                $report = $reports[$dateStr];
                $fullMonthReports[] = [
                    'key' => $dateStr,
                    'id' => $report->id,
                    'date' => $dateStr,
                    'dayName' => $dayName,
                    'workTime' => $report->workTime,
                    'inTime' => $report->inTime,
                    'outTime' => $report->outTime,
                    'shortLeaveTime' => $report->shortLeaveTime,
                    'totalWorkTime' => $report->totalWorkTime,
                    'status' => $report->status,
                ];
                $totalWorkTime += $report->totalWorkTime;
                $totalPresentDays++;
                $totalWorkHours += $report->workTime;
                if ($report->inTime) {
                    $inTimes[] = strtotime($report->inTime);
                }
                if ($report->outTime) {
                    $outTimes[] = strtotime($report->outTime);
                }
            } else {
                $fullMonthReports[] = [
                    'key' => $dateStr,
                    'date' => $dateStr,
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

        $leaveDays = $daysInMonth - $totalPresentDays;

        $averageInTime = !empty($inTimes) ? date('H:i:s', array_sum($inTimes) / count($inTimes)) : null;
        $averageOutTime = !empty($outTimes) ? date('H:i:s', array_sum($outTimes) / count($outTimes)) : null;

        return response()->json([
            'member' => $member,
            'monthSummary' => [
                'month' => $monthStart->format('F'),
                'year' => $year,
                'totalWorkComplete' => $totalWorkTime,
                'averageWorkTime' => $totalPresentDays > 0 ? round($totalWorkTime / $totalPresentDays, 2) : 0,
                'totalPresentDays' => $totalPresentDays,
                'leaveDays' => $leaveDays,
                'totalWorkTimeSum' => $totalWorkHours,
                'averageInTime' => $averageInTime,
                'averageOutTime' => $averageOutTime,
            ],
            'reports' => $fullMonthReports,
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

        $startOfYear = Carbon::create($year, 1, 1);
        $endOfYear   = Carbon::create($year, 12, 31);

        // Start date = member join date or start of year, whichever is later
        $startDate = Carbon::parse($member->joinDate)->max($startOfYear);

        // Last report date for the member in the year
        $lastReportDate = Report::where('memberId', $memberId)
            ->whereYear('date', $year)
            ->max('date');

        $endDate = $lastReportDate ? Carbon::parse($lastReportDate)->min($endOfYear) : $endOfYear;

        // Fetch all reports for member in the year up to last report date
        $allReports = Report::where('memberId', $memberId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Group by month number for correct chronological order
        $monthlyReports = $allReports->groupBy(fn ($r) => Carbon::parse($r->date)->month);

        $monthlySummary = collect(range(1, 12))->map(function ($monthNumber) use ($monthlyReports, $year, $startDate, $endDate) {
            $monthReports = $monthlyReports->get($monthNumber, collect());

            // Days in the month
            $monthStart = Carbon::create($year, $monthNumber, 1);
            $monthEnd   = $monthStart->copy()->endOfMonth();

            // Adjust for member joining mid-month or last report before month end
            if ($monthEnd->lt($startDate) || $monthStart->gt($endDate)) {
                // Member not active in this month
                $daysInMonth = 0;
            } else {
                $monthStart = $monthStart->lt($startDate) ? $startDate : $monthStart;
                $monthEnd   = $monthEnd->gt($endDate) ? $endDate : $monthEnd;
                $daysInMonth = $monthStart->diffInDays($monthEnd) + 1;
            }

            $totalPresent = $monthReports->count();
            $leaveDays = $daysInMonth - $totalPresent;
            $leaveDays = $leaveDays < 0 ? 0 : $leaveDays;

            return [
                'month'             => $monthStart->format('F'),
                'totalWorkComplete' => $monthReports->sum('totalWorkTime'),
                'averageWorkTime'   => $monthReports->count() ? round($monthReports->avg('totalWorkTime'), 2) : 0,
                'totalWorkTimeSum'  => $monthReports->sum('workTime'),
                'totalPresentDays'  => $totalPresent,
                'leaveDays'         => $leaveDays,
            ];
        })->filter(fn ($m) => $m['totalPresentDays'] > 0 || $m['leaveDays'] > 0)->values();

        // Yearly summary
        $totalPresentDays = $allReports->count();
        $totalPlanned = $allReports->sum('workTime');
        $totalActual  = $allReports->sum('totalWorkTime');
        $timeDiff = $totalActual - $totalPlanned;
        $dailyWorkTime = $member->workTime > 0 ? $member->workTime : 1;
        $totalDays = $startDate->diffInDays($endDate) + 1;

        $yearlySummary = [
            'year'              => $year,
            'totalWorkComplete' => $totalActual,
            'averageWorkTime'   => $allReports->count() ? round($allReports->avg('totalWorkTime'), 2) : 0,
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
            'member'         => $member->only(['id', 'name', 'email', 'avatar', 'joinDate', 'workTime', 'status']),
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
            ->max('date');


            $endDate = $lastAttendance ? Carbon::parse($lastAttendance) : $endOfYear;
            $endDate = $lastAttendance ? Carbon::parse($lastAttendance) : $endOfYear;
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
