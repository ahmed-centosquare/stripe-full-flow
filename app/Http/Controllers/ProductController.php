<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
  public function index(Request $request)
  {
    $products = Product::all();
    return view('product.index', compact('products'));
  }

  public function checkout()
  {
    $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_KEY"));

    $products = Product::all();

    $lineItems = [];
    $totalPrice = 0;
    foreach ($products as $product) {
      $totalPrice += $product->price;
      $lineItems[] = [
        'price_data' => [
          'currency' => 'usd',
          'product_data' => [
            'name' => $product->name,
            'images' => [$product->image]
          ],
          'unit_amount' => $product->price * 100,
        ],
        'quantity' => 1,
      ];
    }

    $checkout_session = $stripe->checkout->sessions->create([
      'line_items' => [
        $lineItems
      ],
      'mode' => 'payment',
      'success_url' => route('checkout.success', [], true) . "?session_id={CHECKOUT_SESSION_ID}",
      'cancel_url' => route('checkout.cancel', [], true),
    ]);

    $order = new Order();
    $order->status = 'unpaid';
    $order->total_price = $totalPrice;
    $order->session_id = $checkout_session->id;
    $order->save();

    return redirect($checkout_session->url);
  }

  public function success(Request $request)
  {
    $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_KEY"));
    $sessionId = $request->get('session_id');

    try {
      $session = $stripe->checkout->sessions->retrieve($sessionId);

      if (!$session) {
        throw new NotFoundHttpException();
      }

      $customer = $stripe->customers->retrieve($session->customer);

      $order = Order::where('session_id', $session->id)->first();

      if (!$order) {
        throw new NotFoundHttpException();
      }

      if($order->status == 'unpaid'){  
        $order->status = 'paid';
        $order->save();
      }

      return view('product.checkout-success', compact('customer'));
    } catch (Exception $ex) {
      throw new NotFoundHttpException();
    }
  }

  public function cancel()
  {
  }

  public function webhook()
  {
    $endpoint_secret = env("STRIPE_WEBHOOK_SECRET");

    $payload = @file_get_contents('php://input');
    $event = null;

    try {
      $event = \Stripe\Event::constructFrom(
        json_decode($payload, true)
      );
    } catch (\UnexpectedValueException $e) {
      // Invalid payload
      http_response_code(400);
    }
    if ($endpoint_secret) {
      // Only verify the event if there is an endpoint secret defined
      // Otherwise use the basic decoded event
      $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
      try {
        $event = \Stripe\Webhook::constructEvent(
          $payload,
          $sig_header,
          $endpoint_secret
        );
      } catch (\Stripe\Exception\SignatureVerificationException $e) {
        // Invalid signature
        http_response_code(400);
      }
    }

    // Handle the event
    switch ($event->type) {
      case 'checkout.session.completed':
          $session = $event->data->object;
          $sessionId = $session->id;

          $order = Order::where('session_id', $sessionId)->first();

          if($order && $order->status == 'unpaid'){
            $order->status = 'paid';
            $order->save();
          }
        break;
      default:
        // Unexpected event type
        error_log('Received unknown event type');
    }

    http_response_code(200);
  }
}
