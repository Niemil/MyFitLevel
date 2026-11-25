<?php
declare(strict_types=1);

/* Launch posts. They are created as drafts: read them, fix any detail that is not right, then publish from Admin > Blog. */
return [
[
'slug' => 'why-i-built-myfitlevel',
'title' => 'Why I built MyFitLevel: a free home for people who miss Fitocracy',
'excerpt' => 'I used to log every workout on Fitocracy. When it faded away I went looking for a replacement, could not find one, and decided to build it myself.',
'tags' => 'story,fitocracy,open source',
'body' => <<<'MD'
I logged my training on Fitocracy for years. I was not a casual user: I collected hundreds of thousands of points, chased levels, joined challenges and cheered on strangers who were lifting the same weights as me. It made going to the gym feel like a game I wanted to win, and that was the whole point.

## When it started to fade

First the app became unreliable. Then more and more features stopped working, until I was only getting by on the website. Eventually the site itself was gone. Plenty of other trackers exist, but most are either plain spreadsheets with a nicer font, or they cost money before you can tell whether you will even like them. What I missed was the game: points for every set, levels, quests, and a community that notices when you get stronger.

## Looking for something to replace it

I asked around in the Fitocracy groups and checked every alternative I could find. Some were paid apps with no community yet. Others were early projects that were still months away from being usable. I understood why, because building a social fitness site is a lot of work. But I wanted somewhere to log my lifts now.

## So I built it

Building websites is my hobby. I started years ago with WordPress, then spent time learning JavaScript and Python, and these days I build with modern AI tools alongside my own code. That combination means I can go from idea to a working site far faster than I could before. The first working version, where you could register, log a workout and see points, took a single day. Since then it has been a steady stream of improvements: levels, quests, a weekly boss, groups, teams, gyms, an exercise library, step syncing and an Android app.

I took inspiration from the old site's *idea*, not its code or content. MyFitLevel has its own point model, its own look and its own features, so it is not a copy, and it is not affiliated with Fitocracy.

## What it is, and what it will stay

- **Free.** No ads and no paywall on the basics like logging and levels.
- **Open source.** The code is on GitHub under the AGPL, so anyone can read it, learn from it or help build it.
- **Non-profit.** If running costs ever need help, the plan is optional community support, not locking features away.
- **Privacy-first.** I count visits without tracking individual people, and you can export your data.

## How you can help

The best support costs nothing: [join](/register), log a few workouts, tell me what is missing, and invite a training friend. If you write code, the project is open and contributions are welcome. And if you used to be on Fitocracy, I would love to hear what you miss most. Your list decides what gets built next.

Curious how it compares to the old site? Read [the Fitocracy alternative page](/fitocracy-alternative) or see [how points work](/points).
MD,
],
[
'slug' => 'what-happened-to-fitocracy',
'title' => 'What happened to Fitocracy? A short history of the gamified workout site',
'excerpt' => 'Fitocracy turned lifting into a game, grew to millions of users, was sold in 2016 and was offline by 2024. Here is the timeline and what people still miss.',
'tags' => 'fitocracy,history',
'body' => <<<'MD'
If you are searching for what happened to Fitocracy, here is the short version: it was one of the best-known gamified fitness communities of the 2010s, it changed hands, it slowly stopped working, and as of 2024 the website is offline. Here is the timeline as it is publicly documented.

## The timeline

- **February 2011:** Fitocracy launches, using an invite system to control signups.
- **Late 2011:** it introduces advertising and a paid ad-free option called Fitocracy Hero.
- **2012:** the iOS app arrives, followed by an Android app in early 2013.
- **2014:** it is named one of TIME's best websites of the year.
- **2016:** the company is acquired by a buyer that was not publicly named.
- **2017:** the company announces 12.5 million active users.
- **2024:** the website is reported to be offline.

## Why people loved it

The idea was simple and it worked. You earned **points for every set**, heavier lifts and harder work paid more, and points turned into **levels**. On top of that came achievements, quests, and **props**, the friendly thumbs-up other members gave you for a good workout. The social layer mattered as much as the game: beginners could ask questions, veterans shared programs, and a streak felt like a promise you made to people who would notice if you broke it.

## Why it faded

Nobody outside the company can say exactly why, and it is not fair to guess at internal reasons. What many users noticed is easier to describe: after the sale, updates became rare, the mobile app and some features stopped working one after another, and the community thinned out. When a service depends on an active team to keep it running, it is hard for it to survive without one.

## What people still miss

- Points and levels that make progress visible
- A feed where friends react to your workouts
- Groups, challenges and quests
- A workout log that is fast enough to use between sets

## Where to go now

Several trackers cover the logging part well. Fewer recreate the game and the community. If that is what you miss, [MyFitLevel](/fitocracy-alternative) is a free, community-run project built around the same idea, with its own point system and no affiliation with the original. You can also read [how points work](/points) before you decide.
MD,
],
[
'slug' => 'best-fitocracy-alternatives-2027',
'title' => 'Best Fitocracy alternatives for 2027: what to look for in a workout tracker',
'excerpt' => 'Looking for a Fitocracy alternative? A plain-English guide to the types of workout trackers, what to check before you commit, and where MyFitLevel fits.',
'tags' => 'fitocracy alternative,workout tracker,guide',
'body' => <<<'MD'
Fitocracy was a lifting log wrapped in a game and a community. No single app copies all three parts exactly, so the best Fitocracy alternative depends on which part you miss most. Here is how to choose.

## First, decide what you actually miss

1. **The logging.** You just want a fast, reliable place to record sets, reps and weights.
2. **The game.** You want points, levels, achievements and quests to keep you coming back.
3. **The community.** You want people who see your workouts and cheer you on.

Most apps do the first well, a few do the third, and very few do the second.

## The kinds of alternatives

**Pure loggers.** Apps in the style of Strong or Hevy focus on a clean, fast workout log, charts and routines. If all you need is a notebook with maths, they are excellent. Check each app's current free limits and pricing, because they change.

**Training communities.** Some trackers add social feeds and exercise libraries on top of logging. Good if you want to follow friends and copy routines.

**Gamified fitness sites.** This is the rare category that Fitocracy owned. [MyFitLevel](/fitocracy-alternative) is built for it: points for every set, levels, daily quests, a weekly boss, props and groups. It is independent and not affiliated with Fitocracy.

## A checklist before you commit

- Can you log a workout in under a minute, including on a phone?
- Is it free, and will the basics stay free?
- Can you export your data if you leave?
- Is the project actively maintained? Check for recent updates.
- Is there a community, or will you be logging alone?
- Is your privacy respected? Look for a clear privacy page.

## Where MyFitLevel fits

MyFitLevel is free, has no ads, is open source on GitHub, and is actively developed. You get a quick logger with pre-filled sets, points based on your estimated one-rep max, levels, achievements, teams, gyms, a big exercise library, and an Android app that can sync your steps. It is newer and smaller than the old Fitocracy, so the community is still growing, and that is exactly why early members have such a big say in what gets built.

It does not try to be a clone. The point model, the look and the features are its own, so your old point total will not carry over. See [how points work](/points) for the details.

## The short answer

If you want a plain log, pick any clean logger you like. If you want the game and a friendly community back, give MyFitLevel a try: it takes a minute to [sign up](/register), and you can leave with your data whenever you want.
MD,
],
];
