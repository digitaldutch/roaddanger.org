<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReFrame - Headline Analyzer</title>
    <meta name="description" content="Analyze and improve news headlines about traffic incidents with ReFrame. Understand how to humanize crash reporting.">
    
    <!-- Favicon and icons -->
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta name="theme-color" content="#ffffff">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    
    <!-- html2canvas for image generation -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    
    <style>
        body {
            font-family: 'Crimson Text', serif;
            background-image: url('static/background-2.png');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-position: center;
            box-sizing: border-box;
        }
        
        *, *:before, *:after {
            box-sizing: inherit;
        }
        
        /* Term highlighting styles */
        .headline-term {
            text-decoration: underline;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .headline-term.dark-bg {
            color: white;
            text-decoration-color: white;
        }
        
        .headline-term.dark-bg:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .headline-term.light-bg {
            color: #D32F2F;
            text-decoration-color: #D32F2F;
        }
        
        .headline-term.light-bg:hover {
            background-color: rgba(211, 47, 47, 0.1);
        }
        
        /* Tooltip styles */
        .tooltip {
            position: fixed;
            transform: translate(-50%, -100%);
            z-index: 1000;
            max-width: 300px;
            margin-top: -10px;
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.15));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .tooltip.show {
            opacity: 1;
        }
        
        .tooltip-content {
            background-color: white;
            border: 1px solid #ddd;
            color: #333;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            position: relative;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .tooltip-content:after {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 8px 8px 0;
            border-style: solid;
            border-color: white transparent transparent;
        }
        
        .tooltip p {
            margin: 0;
        }
    </style>
</head>
<body class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 text-black leading-relaxed margin-0 padding-0">
    <div class="max-w-5xl mx-auto">
        <!-- Header Component -->
        <header class="text-center py-12 max-w-full">
            <h1 class="text-6xl md:text-8xl font-medium mb-4 text-black leading-none">ReFrame</h1>
            <p class="text-xl font-medium text-black">Humanizing crash headlines for more accurate reporting</p>
        </header>

        <main>
            <!-- Form Container (hidden when showing results) -->
            <div id="form-container" class="bg-white border border-black p-8 mb-8">
                <!-- Example Articles Component -->
                <div class="mb-8">
                    <p class="text-xl mb-4 text-black">Choose an example or paste your own article:</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <button class="example-btn p-4 flex flex-col text-left border border-black transition-all hover:bg-red-700 hover:text-white hover:border-red-700 h-full" 
                                data-headline="Cyclist dead after East Vancouver crash"
                                data-body="Cyclist dead after East Vancouver crash
A man in his 60s died after being hit by a five-tonne truck while riding his bike in East Vancouver Thursday,

First responders were called to the scene at Kingsway and Nanaimo Street around 12:30 p.m. for reports of the crash,

&quot;Despite life-saving attempts by first responders, the cyclist died at the scene,&quot; the Vancouver Police Department said in a statement, which added that the driver of the truck remained at the scene.

Anyone with information or dash-cam video is urged to call the Collision Investigation Unit at 604-717-3012.">
                            <span class="text-xs uppercase tracking-wide font-medium mb-2">CTV News Vancouver</span>
                            <span class="text-xl font-normal">Cyclist dead after East Vancouver crash</span>
                        </button>

                        <button class="example-btn p-4 flex flex-col text-left border border-black transition-all hover:bg-red-700 hover:text-white hover:border-red-700 h-full"
                                data-headline="One child killed after car ploughs into London primary school"
                                data-body="One child killed after car ploughs into London primary school
LONDON - A girl was killed, and several other children were injured on Thursday after a car ploughed into a primary school building in south-west London, triggering a major response by emergency services.

The crash at the private Study Prep girls' school in Wimbledon, was not being treated by police as terror-related, and the driver – a woman in her 40s who stopped at the scene – was arrested.

She was detained on suspicion of causing death by dangerous driving, London's Metropolitan Police said, confirming the death of the child.

Earlier, the force said seven children and two adults were injured in the crash, and the local MP said he understood several casualties were &quot;being treated as critical&quot;.

Mr Stephen Hammond described the crash as &quot;extraordinarily distressing and tragic&quot;.

Aerial footage of the scene – not far from where the Wimbledon tennis tournament was taking place – showed a Land Rover car stopped at an angle against the wall of the modern school building.">
                            <span class="text-xs uppercase tracking-wide font-medium mb-2">The Straits Times</span>
                            <span class="text-xl font-normal">One child killed after car ploughs into London primary school</span>
                        </button>

                        <button class="example-btn p-4 flex flex-col text-left border border-black transition-all hover:bg-red-700 hover:text-white hover:border-red-700 h-full"
                                data-headline="Amsterdam taxi driver in custody after crash sends pedestrian to the hospital"
                                data-body="Amsterdam taxi driver in custody after crash sends pedestrian to the hospital

One person was injured in Amsterdam after a taxi struck them during the morning rush hour on Monday, police said. The driver of the vehicle was taken into custody for questioning.

The incident happened at about 8:15 a.m. on the west side of Linnaeusstraat near the intersection with Vrolikstraat in Amsterdam-Oost. Several squad cars raced to the scene, and at least two ambulances were dispatched to the location. They injured pedestrian was at the location, and a bicycle on its side was seen on the road.

&quot;The pedestrian was taken to hospital by ambulance,&quot; and Amsterdam police spokesperson told NL Times. Details were not released regarding the victim's age, gender, hometown and condition.">
                            <span class="text-xs uppercase tracking-wide font-medium mb-2">NL Times</span>
                            <span class="text-xl font-normal">Amsterdam taxi driver in custody after crash sends pedestrian to the hospital</span>
                        </button>
                    </div>
                </div>

                <!-- Headline Form Component -->
                <form id="headline-form">
                    <div class="mb-6">
                        <label for="headline" class="block text-xl font-medium text-black mb-3">Headline</label>
                        <input id="headline" type="text" placeholder="Enter a headline to analyze or select an example" 
                               class="w-full p-4 text-lg border border-black focus:outline-none transition-all bg-white">
                    </div>
                    
                    <div class="mb-6">
                        <label for="article-body" class="block text-xl font-medium text-black mb-3">Article Body</label>
                        <textarea id="article-body" placeholder="Enter or paste article body text here or select an example" 
                                  rows="6" class="w-full p-4 text-lg border border-black focus:outline-none transition-all h-48 bg-white resize-none"></textarea>
                    </div>
                    
                    <div>
                        <button type="submit" id="analyze-btn" 
                                class="w-full px-8 py-4 bg-red-700 text-white text-xl font-normal border border-black hover:bg-white hover:text-red-700 hover:border-red-700 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
                            <span id="analyze-text" class="flex items-center justify-center">
                                Analyze Text
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </span>
                            <span id="analyze-loading" class="hidden flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Analyzing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Container (hidden initially) -->
            <div id="results-container" class="hidden">
                <div class="bg-white border border-black p-8 mb-8">
                    <div id="analysis-results"></div>
                </div>
                
                <div>
                    <button id="reset-btn" class="px-8 py-4 bg-black text-white text-xl font-normal border border-black hover:bg-white hover:text-red-700 hover:border-red-700 transition-all">
                        Analyze Another Headline
                    </button>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="mt-12">
            <div class="bg-white border border-black p-8">
                <h2 class="text-3xl font-medium mb-4 text-black">About ReFrame</h2>
                <div class="space-y-4 text-lg">
                    <p>ReFrame is part of a research project at the University of Amsterdam exploring how language shapes our perception of road safety. <br><br> Note: This website uses generative AI.</p>
                    <div class="pt-4">
                        <p class="text-lg text-black">
                            Send me a message (Sahir) at 
                            <span class="underline cursor-pointer transition-all" onclick="copyPhoneNumber('+31616972205')" role="button" tabindex="0" onkeydown="if(event.key === 'Enter' || event.key === ' ') copyPhoneNumber('+31616972205');">
                                +31 6 1697 2205
                            </span>
                            <span id="copy-confirmation" class="ml-2 text-green-600 font-semibold text-lg hidden">Copied!</span>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Tooltip element -->
    <div id="tooltip" class="tooltip">
        <div class="tooltip-content">
            <p id="tooltip-text"></p>
        </div>
    </div>

    <!-- Hidden link for image download -->
    <a id="download-link" class="hidden" href="#" download="reframe-headline-comparison.png">Download</a>

    <script src="js/app.js"></script>
</body>
</html> 