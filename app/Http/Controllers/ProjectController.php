<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    use ApiResponseTrait;

    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index(): JsonResponse
    {
        $projects = $this->projectService->getAllPaginated();
        
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