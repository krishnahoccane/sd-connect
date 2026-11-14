<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Models\Application;
use App\Models\AuditLog;
use App\Modules\Applications\Services\ApplicationService;
use App\Modules\OAuth\Services\OAuthClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly OAuthClientService $oauthClientService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');

        $apps = Application::query()
            ->with(['oauthClientRegistry', 'redirectUris', 'authMethods'])
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $apps]);
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');

        try {
            $result = $this->applicationService->createApplication(
                organisation: $organisation,
                data: $request->validated(),
                actor: $request->user(),
                request: $request
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation_error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'application' => $result['application'],
                'oauth_client' => [
                    'client_id' => $result['client_id'],
                    'client_secret_once' => $result['plain_secret_once'],
                    'secret_notice' => $result['plain_secret_once']
                        ? 'Copy and store this client secret securely now. It will never be displayed again.'
                        : null,
                ],
            ],
        ], 201);
    }

    public function show(Request $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        return response()->json([
            'data' => $application->load(['oauthClientRegistry', 'redirectUris', 'authMethods']),
        ]);
    }

    public function update(UpdateApplicationRequest $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        try {
            $updated = $this->applicationService->updateApplication(
                organisation: $organisation,
                application: $application,
                data: $request->validated(),
                actor: $request->user(),
                request: $request
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation_error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $updated]);
    }

    public function suspend(Request $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        $updated = $this->applicationService->suspendApplication($organisation, $application, $request->user(), $request);
        return response()->json(['data' => $updated]);
    }

    public function activate(Request $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        try {
            $updated = $this->applicationService->activateApplication($organisation, $application, $request->user(), $request);
            return response()->json(['data' => $updated]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation_error', 'message' => $e->getMessage()], 422);
        }
    }

    public function archive(Request $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        $this->applicationService->archiveApplication($organisation, $application, $request->user(), $request);
        return response()->json(['message' => 'Application archived and OAuth client revoked.']);
    }

    public function rotateSecret(Request $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        try {
            $rotated = $this->oauthClientService->rotateSecret($application, $request->user(), $request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation_error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'client_id' => $rotated['client_id'],
                'client_secret_once' => $rotated['plain_secret_once'],
                'rotated_at' => $rotated['rotated_at'],
            ],
        ]);
    }

    public function auditHistory(Request $request, string $organisationUuid, Application $application): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if ($application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_access_denied'], 403);
        }

        $logs = AuditLog::query()
            ->where('organisation_id', $organisation->id)
            ->where('target_type', 'application')
            ->where('target_uuid', $application->uuid)
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json(['data' => $logs]);
    }
}
