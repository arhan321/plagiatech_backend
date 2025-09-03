<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
        ]);

        // sementara dummy response
        $answer = "Ini jawaban AI dummy untuk pertanyaan: " . $request->question;

        // nanti diintegrasikan ke OpenAI GPT API
        return redirect()->route('dashboard')->with('chat_response', $answer);
    }
}
