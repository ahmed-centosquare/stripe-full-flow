<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

    </head>
    <body class="antialiased">
        <div style="display: flex; gap: 3rem;">
            @foreach ($products as $product)
                <div style="flex:1">
                    <img src="{{ $product->image }}" alt="" style="max-width: 100%">
                    <h2>{{ $product->name }}</h2>
                    <p>${{ $product->price }}</p>
                </div>
            @endforeach 
        </div>
        <div>
            <form action="{{ route('checkout') }}" method="POST">
                @csrf
                <button>Checkout</button>
            </form>
        </div>
    </body>
</html>
