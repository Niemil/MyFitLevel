package com.myfitlevel.app

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.text.SpannableStringBuilder
import android.text.style.StyleSpan
import android.graphics.Typeface
import android.view.View
import android.widget.RemoteViews
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.TimeUnit

object Widgets {
    const val ACTION_REFRESH = "com.myfitlevel.app.WIDGET_REFRESH"

    val providers: List<Class<out BaseWidget>> = listOf(
        LevelWidget::class.java, StepsWidget::class.java, BossWidget::class.java, WeekWidget::class.java, ChestWidget::class.java,
        ActionsWidget::class.java, LogWidget::class.java, FeedWidget::class.java, FeedSmallWidget::class.java, MiniWidget::class.java, BundleWidget::class.java,
    )

    fun hasAny(ctx: Context): Boolean {
        val m = AppWidgetManager.getInstance(ctx)
        return providers.any { m.getAppWidgetIds(ComponentName(ctx, it)).isNotEmpty() }
    }

    /** Redraws every widget on the home screen from the cached numbers. */
    fun updateAll(ctx: Context) {
        val m = AppWidgetManager.getInstance(ctx)
        for (p in providers) {
            val ids = m.getAppWidgetIds(ComponentName(ctx, p))
            if (ids.isNotEmpty()) p.getDeclaredConstructor().newInstance().onUpdate(ctx, m, ids)
        }
    }

    fun updateOne(ctx: Context, id: Int) {
        val m = AppWidgetManager.getInstance(ctx)
        val cn = m.getAppWidgetInfo(id)?.provider?.className ?: return
        val w = Class.forName(cn).getDeclaredConstructor().newInstance() as? BaseWidget ?: return
        m.updateAppWidget(id, w.build(ctx, id))
    }

    private val net = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

    fun schedule(ctx: Context) {
        val req = PeriodicWorkRequestBuilder<WidgetWorker>(30, TimeUnit.MINUTES).setConstraints(net).build()
        WorkManager.getInstance(ctx).enqueueUniquePeriodicWork("widgetdata", ExistingPeriodicWorkPolicy.KEEP, req)
    }

    /** Button on a widget: sync steps and reload the numbers right now. */
    fun refreshNow(ctx: Context) {
        Prefs.setRefreshing(ctx, true)
        updateAll(ctx)
        WorkManager.getInstance(ctx).enqueue(OneTimeWorkRequestBuilder<WidgetWorker>().setInputData(androidx.work.workDataOf("steps" to true)).setConstraints(net).build())
    }

    fun refreshIfStale(ctx: Context) {
        if (Prefs.key(ctx).isEmpty() || System.currentTimeMillis() - Prefs.widgetAt(ctx) < 10 * 60_000L) return
        WorkManager.getInstance(ctx).enqueue(OneTimeWorkRequestBuilder<WidgetWorker>().setConstraints(net).build())
    }
}

class WidgetWorker(ctx: Context, p: WorkerParameters) : CoroutineWorker(ctx, p) {
    override suspend fun doWork(): Result {
        val c = applicationContext
        try {
            if (inputData.getBoolean("steps", false) && Prefs.key(c).isNotEmpty()) Prefs.setStatus(c, StepSync.sync(c))
            WidgetData.fetch(c)
        } finally {
            Prefs.setRefreshing(c, false)
            Widgets.updateAll(c)
        }
        return Result.success()
    }
}

abstract class BaseWidget : AppWidgetProvider() {
    abstract val layout: Int
    open val defaultStyle = Style(6, 7, 94, 2, 0, 0)
    open val tools = true
    abstract fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int)

    override fun onUpdate(ctx: Context, mgr: AppWidgetManager, ids: IntArray) {
        for (id in ids) mgr.updateAppWidget(id, build(ctx, id))
        Widgets.refreshIfStale(ctx)
    }

    override fun onAppWidgetOptionsChanged(ctx: Context, mgr: AppWidgetManager, id: Int, o: android.os.Bundle) {
        mgr.updateAppWidget(id, build(ctx, id))
    }

    override fun onEnabled(ctx: Context) { Widgets.schedule(ctx) }

    override fun onDeleted(ctx: Context, ids: IntArray) { for (i in ids) Cfg.clear(ctx, i) }

    override fun onReceive(ctx: Context, intent: Intent) {
        if (intent.action == Widgets.ACTION_REFRESH) Widgets.refreshNow(ctx) else super.onReceive(ctx, intent)
    }

    fun build(ctx: Context, id: Int): RemoteViews {
        val s = Cfg.get(ctx, id, defaultStyle)
        val v = RemoteViews(ctx.packageName, layout)
        v.setImageViewResource(R.id.w_bg, when (s.radius) { 0 -> R.drawable.w_bg_s; 2 -> R.drawable.w_bg_l; else -> R.drawable.w_bg_m })
        v.setInt(R.id.w_bg, "setColorFilter", s.bg)
        v.setInt(R.id.w_bg, "setImageAlpha", s.opacity * 255 / 100)
        val d = WidgetData.cached(ctx)
        if (Prefs.key(ctx).isEmpty() || d == null) {
            v.setViewVisibility(R.id.w_content, View.GONE)
            v.setViewVisibility(R.id.w_tools, View.GONE)
            v.setViewVisibility(R.id.w_msg, View.VISIBLE)
            v.setTextViewText(R.id.w_msg, if (Prefs.key(ctx).isEmpty()) "MyFitLevel\nTap to connect this phone" else "Loading your stats...")
            v.setTextColor(R.id.w_msg, s.text)
            v.setOnClickPendingIntent(R.id.w_root, open(ctx, id, 9, "/steps"))
            return v
        }
        v.setViewVisibility(R.id.w_content, View.VISIBLE)
        v.setViewVisibility(R.id.w_msg, View.GONE)
        render(ctx, v, d, s, id)
        if (tools) {
            v.setViewVisibility(R.id.w_tools, View.VISIBLE)
            val t = if (Prefs.refreshing(ctx)) "updating..." else SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(Prefs.widgetAt(ctx))) + " ⟳"
            v.setTextViewText(R.id.w_updated, t)
            v.setTextColor(R.id.w_updated, s.sub)
            v.setTextColor(R.id.w_cfg, s.sub)
            v.setOnClickPendingIntent(R.id.w_updated, refreshIntent(ctx, id))
            val cfg = Intent(ctx, WidgetConfigActivity::class.java).putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, id)
                .setData(Uri.parse("mflw://cfg/$id")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            v.setOnClickPendingIntent(R.id.w_cfg, PendingIntent.getActivity(ctx, id * 100 + 90, cfg, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT))
        } else {
            v.setViewVisibility(R.id.w_tools, View.GONE)
        }
        return v
    }

    fun open(ctx: Context, id: Int, slot: Int, path: String): PendingIntent {
        val i = Intent(ctx, MainActivity::class.java).putExtra("url", Prefs.SITE + path)
            .setData(Uri.parse("mflw://open/$id/$slot")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        return PendingIntent.getActivity(ctx, id * 100 + slot, i, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
    }

    fun refreshIntent(ctx: Context, id: Int): PendingIntent {
        val i = Intent(ctx, this::class.java).setAction(Widgets.ACTION_REFRESH).setData(Uri.parse("mflw://refresh/$id"))
        return PendingIntent.getBroadcast(ctx, id * 100 + 91, i, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
    }

    protected fun RemoteViews.txt(id: Int, text: CharSequence, color: Int) {
        setTextViewText(id, text)
        setTextColor(id, color)
    }

    protected fun stepStatus(ctx: Context): String {
        val last = Prefs.lastSync(ctx)
        if (last == 0L) return "Steps not synced from this phone yet"
        val st = Prefs.status(ctx)
        val time = SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(last))
        return if (st.startsWith("Synced") || st.startsWith("No steps")) "✓ Synced $time" else "⚠ " + st.take(46)
    }

    protected fun feedLine(who: String, text: String, ago: String): CharSequence {
        val sb = SpannableStringBuilder(who)
        sb.setSpan(StyleSpan(Typeface.BOLD), 0, who.length, 0)
        sb.append("  ").append(text)
        if (ago.isNotEmpty()) sb.append("  ").append(ago)
        return sb
    }
}
