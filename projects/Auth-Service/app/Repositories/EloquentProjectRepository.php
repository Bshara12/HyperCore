<?php

namespace App\Repositories;

use App\Models\Invitation;
use App\Models\Project;
use Exception;
use Illuminate\Support\Facades\DB;

class EloquentProjectRepository implements ProjectRepositoryInterface{
    public function create(array $data): Project {
        $project = Project::create($data);
        DB::table('project_user')
            ->insert([
                    'project_id' => $project->id,
                    'user_id' => $project->owner_id,
                    'role_id' => 1
                ]);
        return $project;
    }

    public function update(Project $project, array $data): bool {
        return $project->update($data);
    }

    public function createInvitation(array $data): Invitation {
        $invitation = Invitation::create($data);
        if(!$invitation) {
            throw new Exception("You Have already invited ". $data['email']);
        }
        return $invitation;
    }

    public function updateInvitation(Invitation $invitation, array $data):bool {
        $updated = $invitation->update($data);
        // $done = DB::table('project_user')->insert([
        //     'project_id' => $invitation->project_id,
        //     'user_id' => $data['data'],
        //     'role_id' => $invitation->role_id
        // ]);
        // if(!$done) {
        //     throw new Exception("Somthing went wrong!");
        // }
        return $updated;
    }
}
