<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        // with('client') optimiza la consulta SQL (Eager Loading)
        return Project::with('client')->latest()->paginate($perPage);
    }

    public function createProject(array $data): Project
    {
        return Project::create($data);
    }

    public function updateProject(Project $project, array $data): Project
    {
        $project->update($data);
        return $project;
    }

    public function deleteProject(Project $project): void
    {
        $project->delete();
    }
}