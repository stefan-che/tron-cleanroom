<?php

namespace App\Http\Controllers;

use App\Http\Resources\Recipient\RecipientResource;
use App\Repositories\RecipientRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipientController extends Controller
{
    public function __construct(
        private readonly RecipientRepository $recipientRepository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int)($request->query('per_page', 30));
        $withWallets = filter_var($request->query('with_wallets', true), FILTER_VALIDATE_BOOLEAN);

        $recipients = $this->recipientRepository
            ->paginate($perPage, $withWallets)
            ->appends($request->query());

        return RecipientResource::collection($recipients);
    }

    public function show(string $identifier): RecipientResource
    {
        $recipient = $this->recipientRepository->findByIdentifier($identifier, withWallets: true);

        if (!$recipient) {
            $this->returnError('Recipient not found');
        }

        return RecipientResource::make($recipient);
    }
}
