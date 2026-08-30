<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use App\Traits\ApiResponseTrait;
use App\Traits\HandlesListQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ApiResponseTrait, HandlesListQueries;

    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index(Request $request): JsonResponse
    {
        $projects = $this->projectService->getAllPaginated(
            $this->perPage($request),
            $this->searchTerm($request)
        );

        return $this->successResponse(
            ProjectResource::collection($projects)->response()->getData(true),
            'Lista de proyectos obtenida.'
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($request->validated());

        return $this->successResponse(
            new ProjectResource($project->load('client')),
            'Proyecto registrado exitosamente.',
            201
        );
    }

    public function show(Project $project): JsonResponse
    {
        return $this->successResponse(
            new ProjectResource($project->load('client')),
            'Detalle del proyecto.'
        );
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $updatedProject = $this->projectService->updateProject($project, $request->validated());

        return $this->successResponse(
            new ProjectResource($updatedProject->load('client')),
            'Proyecto actualizado correctamente.'
        );
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->projectService->deleteProject($project);

        return $this->successResponse(null, 'Proyecto eliminado correctamente.');
    }
}
