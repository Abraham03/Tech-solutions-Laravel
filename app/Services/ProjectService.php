<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    public function getAllPaginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        // with('client') optimiza la consulta SQL (Eager Loading)
        return Project::with('client')
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    // Buscar tambien por cliente: es como se busca un proyecto
                    // en la practica cuando no recuerdas su nombre exacto.
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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
