<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Display a listing of members.
     */
    public function index()
    {
        $members = Member::all();
        return response()->json($members);
    }

    /**
     * Show the form for creating a new member.
     */
    public function create()
    {
        // Not needed for API-based applications
    }

    /**
     * Store a newly created member in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'avatar' => 'nullable|string',
            'joinDate' => 'required|date',
            'endDate' => 'nullable|date',
            'workTime' => 'required|integer',
            'status' => 'required|integer',
        ]);

        $member = Member::create($validated);
        return response()->json($member, 201);
    }

    /**
     * Display the specified member.
     */
    public function show(Member $member)
    {
        return response()->json($member);
    }

    /**
     * Show the form for editing the specified member.
     */
    public function edit(Member $member)
    {
        // Not needed for API-based applications
    }

    /**
     * Update the specified member in storage.
     */
    public function update(Request $request, Member $member)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:members,email,' . $member->id,
            'avatar' => 'nullable|string',
            'joinDate' => 'sometimes|date',
            'endDate' => 'nullable|date',
            'workTime' => 'sometimes|integer',
            'status' => 'sometimes|integer',
        ]);

        $member->update($validated);
        return response()->json($member);
    }

    /**
     * Remove the specified member from storage.
     */
    public function destroy(Member $member)
    {
        $member->delete();
        return response()->json(['message' => 'Member deleted successfully']);
    }
}
