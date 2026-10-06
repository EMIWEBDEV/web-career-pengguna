<?php

namespace App\Http\Controllers\Career\FeedbackAssignment;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackAssignmentController extends Controller
{
    public function list()
    {
        $items = DB::table('N_WEB_CAREERS_Feedback_Assignment as fa')
            ->join('N_WEB_CAREERS_Master_Feedback_Form as ff',
                'fa.Master_Feedback_Form_Id', '=', 'ff.Id_Master_Feedback_Form')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'fa.Program_Id', '=', 'p.Id_Program')
            ->where('fa.Flag_Cancellation', 'T')
            ->select('fa.*', 'ff.Nama as Form_Nama', 'p.Nama as Program_Nama')
            ->orderBy('fa.Created_At', 'DESC')
            ->get();

        return ResponseHelper::success($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_feedback_form_id' => 'required|integer',
            'program_id' => 'nullable|integer',
            'flag_general' => 'required|in:Y,T',
            'flag_aktif' => 'required|in:Y,T',
        ]);

        if ($validated['flag_general'] === 'Y' && $validated['flag_aktif'] === 'Y') {
            $exists = DB::table('N_WEB_CAREERS_Feedback_Assignment')
                ->where('Flag_General', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cancellation', 'T')
                ->exists();
            if ($exists) {
                return ResponseHelper::error('Sudah ada assignment general yang aktif. Nonaktifkan terlebih dahulu.', 409);
            }
        }

        if (! empty($validated['program_id']) && $validated['flag_aktif'] === 'Y') {
            $exists = DB::table('N_WEB_CAREERS_Feedback_Assignment')
                ->where('Master_Feedback_Form_Id', $validated['master_feedback_form_id'])
                ->where('Program_Id', $validated['program_id'])
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cancellation', 'T')
                ->exists();
            if ($exists) {
                return ResponseHelper::success(['skipped' => true], 'Assignment sudah ada — dilewati.');
            }
        }

        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        $id = DB::table('N_WEB_CAREERS_Feedback_Assignment')->insertGetId([
            'Master_Feedback_Form_Id' => $validated['master_feedback_form_id'],
            'Program_Id' => $validated['program_id'] ?? null,
            'Flag_General' => $validated['flag_general'],
            'Flag_Aktif' => $validated['flag_aktif'],
            'Created_At' => $now,
            'Created_By' => $user['nama'] ?? 'SISTEM' ?? 'SISTEM',
            'Updated_At' => $now,
            'Updated_By' => $user['nama'] ?? 'SISTEM' ?? 'SISTEM',
        ], 'Id_Feedback_Assignment');

        return ResponseHelper::success(['id' => $id], 'Assignment berhasil dibuat', 201);
    }

    public function update($id, Request $request)
    {
        $validated = $request->validate([
            'flag_aktif' => 'required|in:Y,T',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $user = session('career_auth');

        DB::table('N_WEB_CAREERS_Feedback_Assignment')
            ->where('Id_Feedback_Assignment', $id)
            ->update([
                'Flag_Aktif' => $validated['flag_aktif'],
                'Updated_At' => $now,
                'Updated_By' => $user['nama'] ?? 'SISTEM' ?? 'SISTEM',
                ]);

        return ResponseHelper::success(null, 'Status assignment diupdate');
    }
}
