<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenSchool - Portale Ricarica</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">

    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <span class="text-2xl text-green-600 font-bold">🌱 GreenSchool</span>
                </div>

                <div class="flex items-center space-x-4">
                    @auth
                        <span class="text-gray-600 text-sm italic">Ciao, {{ Auth::user()->nome }}</span>
                        <a href="/map" class="text-gray-700 hover:text-green-600 font-medium">Mappa</a>
                        
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-semibold">Logout</button>
                        </form>
                    @else
                        <a href="/login" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold">Accedi</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>