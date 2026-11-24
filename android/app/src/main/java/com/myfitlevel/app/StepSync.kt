package com.myfitlevel.app

import android.content.Context
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.permission.HealthPermission
import androidx.health.connect.client.records.DistanceRecord
import androidx.health.connect.client.records.StepsRecord
import androidx.health.connect.client.request.AggregateRequest
import androidx.health.connect.client.time.TimeRangeFilter
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter

object StepSync {
    val stepsPerm = HealthPermission.getReadPermission(StepsRecord::class)
    val distPerm = HealthPermission.getReadPermission(DistanceRecord::class)
    const val bgPerm = "android.permission.health.READ_HEALTH_DATA_IN_BACKGROUND"
    val toRequest: Set<String> = setOf(stepsPerm, distPerm, bgPerm)

    /** Reads yesterday and today from Health Connect and sends the totals to the site. Returns a short status line. */
    suspend fun sync(ctx: Context): String = withContext(Dispatchers.IO) {
        val key = Prefs.key(ctx)
        if (key.length < 20) return@withContext "Paste your API key first (Settings > API access on the site)."
        if (HealthConnectClient.getSdkStatus(ctx) != HealthConnectClient.SDK_AVAILABLE) {
            return@withContext "Health Connect is missing or needs an update (Play Store)."
        }
        try {
            val client = HealthConnectClient.getOrCreate(ctx)
            val granted = client.permissionController.getGrantedPermissions()
            if (stepsPerm !in granted) return@withContext "Allow step access in Health Connect first."
            val metrics = mutableSetOf<androidx.health.connect.client.aggregate.AggregateMetric<*>>(StepsRecord.COUNT_TOTAL)
            if (distPerm in granted) metrics.add(DistanceRecord.DISTANCE_TOTAL)
            val zone = ZoneId.systemDefault()
            val lines = mutableListOf<String>()
            for (offset in 1 downTo 0) {
                val day = LocalDate.now(zone).minusDays(offset.toLong())
                val start = day.atStartOfDay(zone).toInstant()
                val end = if (offset == 0) Instant.now() else day.plusDays(1).atStartOfDay(zone).toInstant()
                val agg = client.aggregate(AggregateRequest(metrics, TimeRangeFilter.between(start, end)))
                val steps = agg[StepsRecord.COUNT_TOTAL] ?: 0L
                val dist = agg[DistanceRecord.DISTANCE_TOTAL]?.inMeters ?: 0.0
                if (steps <= 0L) continue
                val err = post(key, day.format(DateTimeFormatter.ISO_LOCAL_DATE), steps, dist.toLong())
                lines.add(if (err == null) "${day.dayOfMonth}/${day.monthValue}: $steps steps" else err)
            }
            if (lines.isEmpty()) "No steps found yet." else "Synced " + lines.joinToString(", ")
        } catch (e: SecurityException) {
            "Health Connect blocked reading in the background. Open the app and tap Allow."
        } catch (e: Exception) {
            "Sync failed: ${e.message}"
        }
    }

    private fun post(key: String, date: String, steps: Long, distM: Long): String? {
        val c = URL(Prefs.SITE + "/api/v1/steps").openConnection() as HttpURLConnection
        return try {
            c.requestMethod = "POST"
            c.connectTimeout = 15000
            c.readTimeout = 20000
            c.doOutput = true
            c.setRequestProperty("Authorization", "Bearer $key")
            c.setRequestProperty("Content-Type", "application/json")
            val body = JSONObject().put("date", date).put("steps", steps).put("distance_m", distM).toString()
            c.outputStream.use { it.write(body.toByteArray()) }
            when (val code = c.responseCode) {
                200 -> null
                401 -> "The site rejected the API key."
                else -> "Server said $code"
            }
        } finally {
            c.disconnect()
        }
    }
}
