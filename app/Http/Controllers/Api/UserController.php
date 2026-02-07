<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 🔹 عرض كل المستخدمين (Admin فقط)
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required'
                ], 403);
            }

            $users = User::select('id','name','email','type','created_at')->get();

            return response()->json([
                'success' => true,
                'users' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }


    // 🔹 إضافة مستخدم جديد (Admin فقط)
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $data = $request->validate([
                'name' => 'required|string|max:100',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'type' => 'required|in:admin,customer,delivery',
            ]);

            $data['password'] = Hash::make($data['password']);
            $newUser = User::create($data);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $newUser->only(['id','name','email','type'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id){
        try{
       $user=User::findOrfail($id);
       $user->delete();
         return response()->json([
          'success'=>true,
        'message'=>"User Deleted Successfully"
          ],200);

        }catch(\Exception $e){
         return response()->json([
           'success'=>false,
           'message'=>"server Error".$e->getMessage()
        ], 500);
         }
     }
}
