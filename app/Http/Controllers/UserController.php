<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\ShowUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\DestroyUserRequest;
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function index(IndexUserRequest $request)
    {
        $users = User::paginate(10);
        return UserResource::collection($users);
    }
    public function store(StoreUserRequest $request)
    {
        $validate = $request->validated(); 
        $user = User::create($validate);
        return new UserResource($user);
    }
    public function show(ShowUserRequest $request,User $user)
    {
        return new UserResource($user);
    }
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $user->update($validated);
        return new UserResource($user);
    }
    public function destroy(DestroyUserRequest $request, User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }

}
