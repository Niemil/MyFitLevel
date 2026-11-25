package com.myfitlevel.app

import android.content.Context
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import java.util.concurrent.TimeUnit

class SyncWorker(ctx: Context, p: WorkerParameters) : CoroutineWorker(ctx, p) {
    override suspend fun doWork(): Result {
        if (Prefs.key(applicationContext).isNotEmpty()) {
            Prefs.setStatus(applicationContext, StepSync.sync(applicationContext))
            WidgetData.fetch(applicationContext)
            Widgets.updateAll(applicationContext)
        }
        return Result.success()
    }

    companion object {
        /** Sync right now in the background (used when the app is opened), at most every 5 minutes. */
        fun syncSoon(ctx: Context) {
            if (Prefs.key(ctx).isEmpty() || System.currentTimeMillis() - Prefs.lastSync(ctx) < 5 * 60 * 1000L) return
            val req = OneTimeWorkRequestBuilder<SyncWorker>()
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .build()
            WorkManager.getInstance(ctx).enqueue(req)
        }

        fun schedule(ctx: Context) {
            val req = PeriodicWorkRequestBuilder<SyncWorker>(1, TimeUnit.HOURS)
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .build()
            WorkManager.getInstance(ctx).enqueueUniquePeriodicWork("steps", ExistingPeriodicWorkPolicy.KEEP, req)
        }
    }
}
