<?php

namespace App\Http\Controllers\Transaction\Hse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Master\Estate;
use App\Models\Master\Employee;
use App\Models\Transaction\Hse\EmployeeTrainingResult;

/**
 * Training Report Controller
 * Handles HSE Training Reporting, Individual Training Records (FM_CW_047), and Analytics
 */
class TrainingReportController extends Controller
{
    /**
     * Individual Employee Training Report Listing
     */
    public function individual(Request $request)
    {
        $employeeCode = $request->get('employee_code');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = DB::table('t_employee_training_result as etr')
            ->select([
                'etr.result_id',
                'etr.employee_code',
                'etr.employee_name',
                'etr.course_no',
                'mc.course_name',
                'mc.course_category',
                'mc.course_type',
                'etr.training_date',
                'etr.training_result_type',
                'etr.training_result_score',
                'etr.assessment_method',
                'etr.assessment_by',
                'etr.attendance_status',
                'me.employee_profile',
                'me.employee_estate_code',
                'me.employee_division_code',
                'me.employee_valid_from',
                'mes.estate_name',
            ])
            ->leftJoin('m_course as mc', 'etr.course_no', '=', 'mc.course_no_epms')
            ->leftJoin('m_employee as me', 'etr.employee_code', '=', 'me.employee_code')
            ->leftJoin('m_estate as mes', 'me.employee_estate_code', '=', 'mes.estate_code');

        if (!empty($employeeCode)) {
            $query->where(function ($q) use ($employeeCode) {
                $q->where('etr.employee_code', 'ILIKE', "%{$employeeCode}%")
                  ->orWhere('etr.employee_name', 'ILIKE', "%{$employeeCode}%");
            });
        }

        if (!empty($fromDate)) {
            $query->where('etr.training_date', '>=', date('Y-m-d', strtotime($fromDate)));
        }

        if (!empty($toDate)) {
            $query->where('etr.training_date', '<=', date('Y-m-d', strtotime($toDate)));
        }

        $records = $query->orderBy('etr.training_date', 'DESC')->paginate(15);

        return view('transaction.hse.train.report.individual', [
            'records'      => $records,
            'employeeCode' => $employeeCode,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
        ]);
    }

    /**
     * Print FM_CW_047 Individual Training Record (ISO 45001:2018 Standard)
     */
    public function printIndividual(Request $request)
    {
        $employeeCode = $request->get('employee_code');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = DB::table('t_employee_training_result as etr')
            ->select([
                'etr.employee_code',
                'etr.employee_name',
                'etr.course_no',
                'mc.course_name',
                'mc.course_category',
                'mc.course_type',
                'etr.training_date',
                'etr.training_result_type',
                'etr.training_result_score',
                'etr.assessment_method',
                'etr.assessment_by',
                'etr.attendance_status',
                'me.employee_profile',
                'me.employee_estate_code',
                'me.employee_division_code',
                'me.employee_valid_from',
                'mes.estate_name',
            ])
            ->leftJoin('m_course as mc', 'etr.course_no', '=', 'mc.course_no_epms')
            ->leftJoin('m_employee as me', 'etr.employee_code', '=', 'me.employee_code')
            ->leftJoin('m_estate as mes', 'me.employee_estate_code', '=', 'mes.estate_code');

        if (!empty($employeeCode)) {
            $query->where(function ($q) use ($employeeCode) {
                $q->where('etr.employee_code', 'ILIKE', "%{$employeeCode}%")
                  ->orWhere('etr.employee_name', 'ILIKE', "%{$employeeCode}%");
            });
        }

        if (!empty($fromDate)) {
            $query->where('etr.training_date', '>=', date('Y-m-d', strtotime($fromDate)));
        }

        if (!empty($toDate)) {
            $query->where('etr.training_date', '<=', date('Y-m-d', strtotime($toDate)));
        }

        $trainingRecords = $query->orderBy('etr.training_date', 'DESC')->get();

        // Build estate code -> estate name lookup map from m_estate
        $estateMap = [];
        $estates = DB::table('m_estate')->select('estate_code', 'estate_name')->get();
        foreach ($estates as $est) {
            $estateMap[strtoupper(trim($est->estate_code))] = trim($est->estate_name);
        }

        $sessionEstateCode = strtoupper(trim(session('estate', session('estate_code', ''))));
        $defaultEstateName = $estateMap[$sessionEstateCode] ?? ($sessionEstateCode ?: '-');

        // Group records by employee for FM_CW_047 Individual Training Record
        $groupedRecords = [];
        if ($trainingRecords->isNotEmpty()) {
            foreach ($trainingRecords as $rec) {
                $code = $rec->employee_code;
                if (!isset($groupedRecords[$code])) {
                    // Resolve Estate Name (e.g. translate 'SM' to 'SEGAMAT ESTATE')
                    $estateName = '';
                    if (!empty($rec->estate_name)) {
                        $estateName = $rec->estate_name;
                    } elseif (!empty($rec->employee_estate_code) && isset($estateMap[strtoupper(trim($rec->employee_estate_code))])) {
                        $estateName = $estateMap[strtoupper(trim($rec->employee_estate_code))];
                    } elseif (!empty($rec->employee_profile)) {
                        $prof = strtoupper(trim($rec->employee_profile));
                        $estateName = $estateMap[$prof] ?? $rec->employee_profile;
                    }

                    if (empty($estateName) || isset($estateMap[strtoupper(trim($estateName))])) {
                        $estateName = isset($estateMap[strtoupper(trim($estateName))]) ? $estateMap[strtoupper(trim($estateName))] : $defaultEstateName;
                    }

                    $startDate = (!empty($rec->employee_valid_from) && $rec->employee_valid_from !== '1970-01-01') ? date('d/m/Y', strtotime($rec->employee_valid_from)) : '-';
                    $groupedRecords[$code] = [
                        'employee_code' => $rec->employee_code,
                        'employee_name' => $rec->employee_name,
                        'estate_name'   => $estateName,
                        'passport_no'   => '-',
                        'start_date'    => $startDate,
                        'records'       => [],
                    ];
                }
                $groupedRecords[$code]['records'][] = $rec;
            }
        } elseif (!empty($employeeCode)) {
            // Check if employee exists in master to print a blank record form
            $emp = DB::table('m_employee')->where('employee_code', $employeeCode)->first();
            if ($emp) {
                $estateName = '';
                if (!empty($emp->employee_estate_code) && isset($estateMap[strtoupper(trim($emp->employee_estate_code))])) {
                    $estateName = $estateMap[strtoupper(trim($emp->employee_estate_code))];
                } elseif (!empty($emp->employee_profile)) {
                    $prof = strtoupper(trim($emp->employee_profile));
                    $estateName = $estateMap[$prof] ?? $emp->employee_profile;
                }
                if (empty($estateName) || isset($estateMap[strtoupper(trim($estateName))])) {
                    $estateName = isset($estateMap[strtoupper(trim($estateName))]) ? $estateMap[strtoupper(trim($estateName))] : $defaultEstateName;
                }

                $startDate = (!empty($emp->employee_valid_from) && $emp->employee_valid_from !== '1970-01-01') ? date('d/m/Y', strtotime($emp->employee_valid_from)) : '-';
                $groupedRecords[$employeeCode] = [
                    'employee_code' => $emp->employee_code,
                    'employee_name' => $emp->employee_name,
                    'estate_name'   => $estateName,
                    'passport_no'   => '-',
                    'start_date'    => $startDate,
                    'records'       => [],
                ];
            }
        }

        $data = [
            'title'           => 'FM_CW_047 Individual Training Record',
            'doc_ref_no'      => 'IOI/OHSMS/FM/CW/047/00',
            'revision_date'   => date('d/m/Y'), // Sementara diisi tanggal print
            'grouped_records' => $groupedRecords,
            'employee_code'   => $employeeCode ?: 'ALL',
            'from_date'       => $fromDate,
            'to_date'         => $toDate,
            'print_date'      => date('d/m/Y H:i:s'),
        ];

        return view('transaction.hse.train.report.individual_print', $data);
    }
}
