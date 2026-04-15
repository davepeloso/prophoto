{{-- Story 3.3 — Identity gate for proofing galleries --}}
{{-- Visitor must confirm their email before accessing the proofing gallery --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Identity — {{ $gallery->subject_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md mx-auto px-6">
        {{-- Gallery name --}}
        <div class="text-center mb-8">
            <h1 class="text-2xl font-light tracking-wide">{{ $gallery->subject_name }}</h1>
            <p class="text-gray-400 text-sm mt-2">Please confirm your email to view this gallery.</p>
        </div>

        {{-- Photographer's Message --}}
        @if($share->message)
            <div class="mb-6">
                <div class="border-l-2 border-gray-700 pl-4 py-2">
                    <p class="text-gray-300 text-sm leading-relaxed italic">{!! nl2br(e($share->message)) !!}</p>
                </div>
            </div>
        @endif

        {{-- Identity form --}}
        <form method="POST" action="{{ route('gallery.viewer.confirm', ['token' => $share->share_token]) }}" class="space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                    Email Address
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $share->shared_with_email) }}"
                    required
                    autofocus
                    placeholder="you@example.com"
                    class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/30 focus:border-transparent transition-colors"
                >

                @if(isset($errors) && $errors->has('email'))
                    <p class="mt-2 text-sm text-red-400">{{ $errors->first('email') }}</p>
                @endif
            </div>

            <button
                type="submit"
                class="w-full px-4 py-3 bg-white text-gray-950 font-medium rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
            >
                Continue to Gallery
            </button>
        </form>

        <p class="text-center text-xs text-gray-600 mt-8">
            Your email is used to track your selections in this gallery.
        </p>
    </div>

</body>
</html>
