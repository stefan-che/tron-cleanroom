<?php

namespace App\Http\Controllers;

use App\Http\Resources\Energy\EnergyWalletResource;
use App\Repositories\Energy\EnergyWalletRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnergyWalletController extends Controller
{
    public function __construct(
        private readonly EnergyWalletRepository $repository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $perPage = min(100, max(1, (int)$request->query('per_page', 30)));
        $page = max(1, (int)$request->query('page', 1));
        $search = $request->query('search');
        $withRecipient = filter_var($request->query('with_recipient', false), FILTER_VALIDATE_BOOLEAN);
        $withoutRecipient = !$withRecipient;

        if ($search) {
            $items = $this->repository->search((string)$search, $perPage, withoutRecipient: $withoutRecipient);
            return EnergyWalletResource::collection($items);
        }

        $onlyEnabled = filter_var($request->query('only_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $total = $this->repository->total($onlyEnabled, withoutRecipient: $withoutRecipient);
        $items = $this->repository->getPage($page, $perPage, $onlyEnabled, withoutRecipient: $withoutRecipient);

        return EnergyWalletResource::collection($items)->additional([
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(int $id): EnergyWalletResource|JsonResponse
    {
        $wallet = $this->repository->findWithRecipient($id);

        if (!$wallet) {
            $this->returnError('Wallet not found');
        }

        return EnergyWalletResource::make($wallet);
    }
}
