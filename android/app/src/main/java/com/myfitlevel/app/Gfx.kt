package com.myfitlevel.app

import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.RectF

/** Small bitmaps for progress bars and rings, so every widget colour can be customised. */
object Gfx {
    fun bar(pct: Int, fg: Int, track: Int, w: Int = 480, h: Int = 24): Bitmap {
        val b = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
        val c = Canvas(b)
        val p = Paint(Paint.ANTI_ALIAS_FLAG)
        p.color = track
        c.drawRoundRect(RectF(0f, 0f, w.toFloat(), h.toFloat()), h / 2f, h / 2f, p)
        val fw = w * pct.coerceIn(0, 100) / 100f
        if (fw > 0f) {
            p.color = fg
            c.drawRoundRect(RectF(0f, 0f, maxOf(fw, h.toFloat()), h.toFloat()), h / 2f, h / 2f, p)
        }
        return b
    }

    fun ring(pct: Int, fg: Int, track: Int, size: Int = 200, stroke: Float = 22f): Bitmap {
        val b = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        val c = Canvas(b)
        val p = Paint(Paint.ANTI_ALIAS_FLAG)
        p.style = Paint.Style.STROKE
        p.strokeWidth = stroke
        p.strokeCap = Paint.Cap.ROUND
        val r = RectF(stroke / 2, stroke / 2, size - stroke / 2, size - stroke / 2)
        p.color = track
        c.drawArc(r, 0f, 360f, false, p)
        if (pct > 0) {
            p.color = fg
            c.drawArc(r, -90f, 360f * pct.coerceIn(1, 100) / 100f, false, p)
        }
        return b
    }
}
