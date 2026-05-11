<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\DTOs\Order\CreateOrderDTO;
use App\Domains\E_Commerce\Requests\CreateOrderRequest;
use App\Domains\E_Commerce\Requests\UpdateOrderStatusRequest;
use App\Domains\E_Commerce\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function store(CreateOrderRequest $request)
    {
        $dto = CreateOrderDTO::fromRequest($request);
        $order = $this->orderService->createFromCart($dto);

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order,
        ]);
    }

    public function index(Request $request)
    {
        $userId = $request->attributes->get('auth_user')['id'];
        $projectId = $request->project_id;

        $orders = $this->orderService->listOrders($projectId, $userId);

        return response()->json([
            'message' => 'Orders fetched successfully',
            'data' => $orders,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $projectId = $request->project_id;

        $filters = [
            'status' => $request->query('status'),
            'user_id' => $request->query('user_id'),
        ];

        $orders = $this->orderService->adminListOrders($projectId, $filters);

        return response()->json([
            'message' => 'Admin orders fetched successfully',
            'data' => $orders,
        ]);
    }

    public function show(Request $request, int $orderId)
    {
        $userId = $request->attributes->get('auth_user')['id'];
        $projectId = $request->project_id;

        $order = $this->orderService->getOrderDetails($orderId, $projectId, $userId);

        return response()->json([
            'message' => 'Order fetched successfully',
            'data' => $order,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId)
    {
        $projectId = $request->project_id;

        $order = $this->orderService->updateOrderStatus(
            $orderId,
            $projectId,
            $request->status
        );

        return response()->json([
            'message' => 'Order status updated successfully',
            'data' => $order,
        ]);
    }
}
