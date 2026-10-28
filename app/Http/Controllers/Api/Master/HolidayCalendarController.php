<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\HolidayCalendar;
use Illuminate\Http\Request;

class HolidayCalendarController extends Controller
{
    public function index(Request $request)
    {
        $query = HolidayCalendar::query()->where('is_active', true)->orderBy('date');
        if ($request->boolean('options')) {
            return response()->json(['success' => true, 'data' => $query->get(['id', 'date', 'name', 'type', 'is_active'])]);
        }
        return response()->json(['success' => true, 'data' => $query->paginate($request->integer('per_page', 50))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holiday_calendars,date'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:national_holiday,cuti_bersama'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['created_by'] = $request->user()?->id;
        return response()->json(['success' => true, 'data' => HolidayCalendar::create($data)], 201);
    }

    public function update(Request $request, string $id)
    {
        $holiday = HolidayCalendar::findOrFail($id);
        $data = $request->validate([
            'date' => ['sometimes', 'date', 'unique:holiday_calendars,date,'.$holiday->id],
            'name' => ['sometimes', 'string', 'max:160'],
            'type' => ['sometimes', 'in:national_holiday,cuti_bersama'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['updated_by'] = $request->user()?->id;
        $holiday->update($data);
        return response()->json(['success' => true, 'data' => $holiday->fresh()]);
    }

    public function destroy(string $id)
    {
        HolidayCalendar::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
