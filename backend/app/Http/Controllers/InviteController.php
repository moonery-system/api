<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Utils\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function validateToken(Request $request){
        $token = $request->query('token');

        if(!$token) return ApiResponse::unauthorized();

        $invite = Invite::where('token', $token)->first();

        if(!$invite || $invite->expires_at < Carbon::now()) return ApiResponse::unauthorized();

        return ApiResponse::success();
    }
}
