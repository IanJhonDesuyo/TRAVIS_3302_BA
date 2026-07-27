from __future__ import annotations

from pathlib import Path
from typing import Any
import calendar
import logging
import sys

import joblib
import pandas as pd
from flask import Flask, jsonify, request
from flask_cors import CORS


# ============================================================
# TRAVIS MACHINE LEARNING API
#
# Endpoints:
#   GET  /health
#   GET  /model-info
#   POST /predict/monthly
#   GET  /hotspots
#   GET  /hotspots/<risk_level>
# ============================================================


BASE_DIR = Path(__file__).resolve().parent
MODEL_DIR = BASE_DIR / "models"
RESULTS_DIR = BASE_DIR / "results"

# The authenticated PHP layout starts this service with pythonw.exe to avoid
# opening a console window. Flask/Click still prints a startup banner, so give
# it valid streams before logging is configured; otherwise Python raises
# OSError 22 and exits immediately after loading the models.
if Path(sys.executable).name.lower() == "pythonw.exe":
    _service_log = open(
        BASE_DIR / "ml-api.log",
        "a",
        encoding="utf-8",
        buffering=1,
    )
    sys.stdout = _service_log
    sys.stderr = _service_log

MONTHLY_MODEL_PATH = MODEL_DIR / "monthly_risk_model.pkl"
HOTSPOT_MODEL_PATH = MODEL_DIR / "hotspot_clustering_model.pkl"
HOTSPOT_RESULTS_PATH = RESULTS_DIR / "hotspot_clusters.csv"


app = Flask(__name__)

# Allows your PHP/JavaScript frontend to request this API.
CORS(app)


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s",
)

logger = logging.getLogger("travis_ml_api")


# ============================================================
# GLOBAL MODEL STORAGE
# ============================================================

monthly_model_bundle: dict[str, Any] | None = None
hotspot_model_bundle: dict[str, Any] | None = None
hotspot_results: pd.DataFrame | None = None

monthly_model_error: str | None = None
hotspot_model_error: str | None = None
hotspot_results_error: str | None = None


# ============================================================
# MODEL LOADING
# ============================================================

def load_models() -> None:
    """Load all trained models and hotspot results."""

    global monthly_model_bundle
    global hotspot_model_bundle
    global hotspot_results

    global monthly_model_error
    global hotspot_model_error
    global hotspot_results_error

    # Monthly model
    try:
        if not MONTHLY_MODEL_PATH.exists():
            raise FileNotFoundError(
                f"Monthly model not found: {MONTHLY_MODEL_PATH}"
            )

        monthly_model_bundle = joblib.load(
            MONTHLY_MODEL_PATH
        )

        if not isinstance(monthly_model_bundle, dict):
            raise TypeError(
                "The monthly model file must contain a model bundle dictionary."
            )

        if "model" not in monthly_model_bundle:
            raise KeyError(
                "The monthly model bundle does not contain the 'model' key."
            )

        monthly_model_error = None
        logger.info("Monthly risk model loaded successfully.")

    except Exception as error:
        monthly_model_bundle = None
        monthly_model_error = str(error)
        logger.exception("Unable to load monthly model.")

    # Hotspot clustering model
    try:
        if not HOTSPOT_MODEL_PATH.exists():
            raise FileNotFoundError(
                f"Hotspot model not found: {HOTSPOT_MODEL_PATH}"
            )

        hotspot_model_bundle = joblib.load(
            HOTSPOT_MODEL_PATH
        )

        if not isinstance(hotspot_model_bundle, dict):
            raise TypeError(
                "The hotspot model file must contain a model bundle dictionary."
            )

        hotspot_model_error = None
        logger.info("Hotspot clustering model loaded successfully.")

    except Exception as error:
        hotspot_model_bundle = None
        hotspot_model_error = str(error)
        logger.exception("Unable to load hotspot model.")

    # Hotspot cluster output
    try:
        if not HOTSPOT_RESULTS_PATH.exists():
            raise FileNotFoundError(
                f"Hotspot results not found: {HOTSPOT_RESULTS_PATH}"
            )

        hotspot_results = pd.read_csv(
            HOTSPOT_RESULTS_PATH
        )

        hotspot_results.columns = (
            hotspot_results.columns
            .astype(str)
            .str.strip()
        )

        required_columns = {
            "Location",
            "Cluster",
            "Risk Level",
        }

        missing_columns = required_columns.difference(
            hotspot_results.columns
        )

        if missing_columns:
            raise ValueError(
                "The hotspot results file is missing columns: "
                f"{sorted(missing_columns)}"
            )

        hotspot_results_error = None
        logger.info("Hotspot cluster results loaded successfully.")

    except Exception as error:
        hotspot_results = None
        hotspot_results_error = str(error)
        logger.exception("Unable to load hotspot results.")


load_models()


# ============================================================
# RESPONSE HELPERS
# ============================================================

def success_response(
    data: Any = None,
    message: str = "Request completed successfully.",
    status_code: int = 200,
):
    return (
        jsonify(
            {
                "success": True,
                "message": message,
                "data": data,
            }
        ),
        status_code,
    )


def error_response(
    message: str,
    status_code: int = 400,
    details: Any = None,
):
    response = {
        "success": False,
        "message": message,
    }

    if details is not None:
        response["details"] = details

    return jsonify(response), status_code


# ============================================================
# VALIDATION
# ============================================================

def parse_year(value: Any) -> int:
    try:
        year = int(value)
    except (TypeError, ValueError) as error:
        raise ValueError(
            "Year must be a valid whole number."
        ) from error

    if year < 2000 or year > 2100:
        raise ValueError(
            "Year must be between 2000 and 2100."
        )

    return year


def parse_month(value: Any) -> int:
    try:
        month = int(value)
    except (TypeError, ValueError) as error:
        raise ValueError(
            "Month must be a valid whole number."
        ) from error

    if month < 1 or month > 12:
        raise ValueError(
            "Month must be between 1 and 12."
        )

    return month


# ============================================================
# DECISION-SUPPORT RECOMMENDATIONS
# ============================================================

def recommendations_for_risk(
    risk_level: str,
) -> list[str]:
    normalized_risk = risk_level.strip().lower()

    recommendations = {
        "low": [
            "Continue routine traffic monitoring.",
            "Maintain the regular traffic-enforcer schedule.",
            "Review current conditions before changing deployment.",
        ],
        "low risk": [
            "Continue routine traffic monitoring.",
            "Maintain the regular traffic-enforcer schedule.",
            "Review current conditions before changing deployment.",
        ],
        "medium": [
            "Increase patrol visibility during peak traffic periods.",
            "Prepare additional personnel for known hotspot locations.",
            "Monitor recurring traffic violations more closely.",
        ],
        "medium risk": [
            "Increase patrol visibility during peak traffic periods.",
            "Prepare additional personnel for known hotspot locations.",
            "Monitor recurring traffic violations more closely.",
        ],
        "high": [
            "Deploy additional traffic enforcers to priority hotspots.",
            "Increase patrol frequency during peak traffic periods.",
            "Closely monitor congestion and possible road incidents.",
            "Prepare public traffic advisories when necessary.",
        ],
        "high risk": [
            "Deploy additional traffic enforcers to priority hotspots.",
            "Increase patrol frequency during peak traffic periods.",
            "Closely monitor congestion and possible road incidents.",
            "Prepare public traffic advisories when necessary.",
        ],
    }

    return recommendations.get(
        normalized_risk,
        [
            "Review the prediction with current TMO monitoring data.",
        ],
    )


# ============================================================
# MONTHLY PREDICTION
# ============================================================

def predict_monthly_risk(
    year: int,
    month: int,
) -> dict[str, Any]:
    if monthly_model_bundle is None:
        raise RuntimeError(
            monthly_model_error
            or "The monthly model is unavailable."
        )

    model = monthly_model_bundle["model"]

    input_data = pd.DataFrame(
        [
            {
                "Year": year,
                "Month": month,
            }
        ]
    )

    predicted_risk = str(
        model.predict(input_data)[0]
    )

    confidence = None
    probabilities_result = []

    if hasattr(model, "predict_proba"):
        probabilities = model.predict_proba(
            input_data
        )[0]

        classes = model.classes_

        confidence = round(
            float(max(probabilities)) * 100,
            2,
        )

        ranked_indexes = probabilities.argsort()[::-1]

        probabilities_result = [
            {
                "risk_level": str(classes[index]),
                "probability": round(
                    float(probabilities[index]) * 100,
                    2,
                ),
            }
            for index in ranked_indexes
        ]

    month_name = calendar.month_name[month]

    return {
        "year": year,
        "month": month,
        "month_name": month_name,
        "risk_level": predicted_risk,
        "confidence": confidence,
        "probabilities": probabilities_result,
        "recommendations": recommendations_for_risk(
            predicted_risk
        ),
        "model": "Random Forest Classifier",
        "note": (
            "This prediction is based on historical TMO records "
            "and should be reviewed together with current traffic conditions."
        ),
    }


# ============================================================
# HOTSPOT RESULTS
# ============================================================

def hotspot_records(
    risk_level: str | None = None,
) -> list[dict[str, Any]]:
    if hotspot_results is None:
        raise RuntimeError(
            hotspot_results_error
            or "The hotspot cluster results are unavailable."
        )

    result_df = hotspot_results.copy()

    if risk_level:
        normalized = risk_level.replace("-", " ").strip().lower()

        result_df = result_df[
            result_df["Risk Level"]
            .astype(str)
            .str.lower()
            == normalized
        ]

    risk_order = {
        "High Risk": 0,
        "Medium Risk": 1,
        "Low Risk": 2,
    }

    result_df["_risk_order"] = (
        result_df["Risk Level"]
        .map(risk_order)
        .fillna(99)
    )

    total_column = None

    for candidate in [
        "Total Violations",
        "Total_Violations",
        "Total",
    ]:
        if candidate in result_df.columns:
            total_column = candidate
            break

    sort_columns = ["_risk_order"]
    ascending = [True]

    if total_column:
        sort_columns.append(total_column)
        ascending.append(False)

    result_df = (
        result_df.sort_values(
            sort_columns,
            ascending=ascending,
        )
        .drop(columns=["_risk_order"])
    )

    result_df = result_df.where(
        pd.notna(result_df),
        None,
    )

    return result_df.to_dict(
        orient="records"
    )


# ============================================================
# API ROUTES
# ============================================================

@app.get("/")
def home():
    return success_response(
        data={
            "service": "TRAVIS Machine Learning API",
            "version": "1.0",
            "available_endpoints": [
                "GET /health",
                "GET /model-info",
                "POST /predict/monthly",
                "GET /hotspots",
                "GET /hotspots/<risk_level>",
                "POST /reload-models",
            ],
        },
        message="TRAVIS Machine Learning API is running.",
    )


@app.get("/health")
def health():
    monthly_ready = monthly_model_bundle is not None
    hotspot_model_ready = hotspot_model_bundle is not None
    hotspot_results_ready = hotspot_results is not None

    overall_ready = (
        monthly_ready
        and hotspot_model_ready
        and hotspot_results_ready
    )

    status_code = 200 if overall_ready else 503

    return (
        jsonify(
            {
                "success": overall_ready,
                "service": "TRAVIS Machine Learning API",
                "status": (
                    "healthy"
                    if overall_ready
                    else "partially unavailable"
                ),
                "models": {
                    "monthly_model": {
                        "loaded": monthly_ready,
                        "error": monthly_model_error,
                    },
                    "hotspot_model": {
                        "loaded": hotspot_model_ready,
                        "error": hotspot_model_error,
                    },
                    "hotspot_results": {
                        "loaded": hotspot_results_ready,
                        "error": hotspot_results_error,
                    },
                },
            }
        ),
        status_code,
    )


@app.get("/model-info")
def model_info():
    monthly_information = None
    hotspot_information = None

    if monthly_model_bundle is not None:
        monthly_information = {
            "features": monthly_model_bundle.get(
                "features",
                ["Year", "Month"],
            ),
            "classes": monthly_model_bundle.get(
                "classes",
                [],
            ),
            "low_threshold": monthly_model_bundle.get(
                "low_threshold"
            ),
            "high_threshold": monthly_model_bundle.get(
                "high_threshold"
            ),
            "algorithm": "Random Forest Classifier",
        }

    if hotspot_model_bundle is not None:
        hotspot_information = {
            "algorithm": hotspot_model_bundle.get(
                "algorithm",
                "K-Means Clustering",
            ),
            "model_version": hotspot_model_bundle.get(
                "model_version",
                "1.0",
            ),
            "feature_count": len(
                hotspot_model_bundle.get(
                    "feature_columns",
                    [],
                )
            ),
            "risk_mapping": hotspot_model_bundle.get(
                "risk_mapping",
                {},
            ),
        }

    return success_response(
        data={
            "monthly_model": monthly_information,
            "hotspot_model": hotspot_information,
        }
    )


@app.post("/predict/monthly")
def monthly_prediction():
    if monthly_model_bundle is None:
        return error_response(
            "Monthly prediction model is unavailable.",
            503,
            monthly_model_error,
        )

    payload = request.get_json(
        silent=True
    )

    if payload is None:
        return error_response(
            "The request body must contain valid JSON.",
            400,
        )

    try:
        year = parse_year(
            payload.get("year")
        )
        month = parse_month(
            payload.get("month")
        )

        prediction = predict_monthly_risk(
            year,
            month,
        )

        return success_response(
            data=prediction,
            message="Monthly risk prediction completed.",
        )

    except ValueError as error:
        return error_response(
            str(error),
            422,
        )

    except Exception as error:
        logger.exception(
            "Monthly prediction failed."
        )

        return error_response(
            "Unable to generate the monthly prediction.",
            500,
            str(error),
        )


@app.get("/hotspots")
def all_hotspots():
    try:
        records = hotspot_records()

        return success_response(
            data={
                "count": len(records),
                "locations": records,
                "source": "trained_hotspot_clustering_results",
                "algorithm": hotspot_model_bundle.get("algorithm", "K-Means Clustering") if hotspot_model_bundle else "K-Means Clustering",
                "model_version": hotspot_model_bundle.get("model_version") if hotspot_model_bundle else None,
            },
            message="Hotspot locations retrieved successfully.",
        )

    except Exception as error:
        logger.exception("Unable to retrieve hotspots.")
        return error_response("Unable to retrieve hotspot results.", 500, str(error))


@app.get("/hotspots/<risk_level>")
def hotspots_by_risk(risk_level: str):
    allowed_values = {
        "high",
        "high-risk",
        "medium",
        "medium-risk",
        "low",
        "low-risk",
    }

    normalized = risk_level.strip().lower()

    if normalized not in allowed_values:
        return error_response(
            "Invalid risk level. Use high-risk, medium-risk, or low-risk.",
            422,
        )

    formatted_risk = (
        normalized
        .replace("-", " ")
        .title()
    )

    if formatted_risk in {
        "High",
        "Medium",
        "Low",
    }:
        formatted_risk += " Risk"

    try:
        records = hotspot_records(
            formatted_risk
        )

        return success_response(
            data={
                "risk_level": formatted_risk,
                "count": len(records),
                "locations": records,
                "recommendations": recommendations_for_risk(
                    formatted_risk
                ),
            },
            message=f"{formatted_risk} hotspots retrieved successfully.",
        )

    except Exception as error:
        logger.exception(
            "Unable to retrieve risk-filtered hotspots."
        )

        return error_response(
            "Unable to retrieve hotspot results.",
            500,
            str(error),
        )


@app.post("/reload-models")
def reload_models():
    load_models()

    all_loaded = (
        monthly_model_bundle is not None
        and hotspot_model_bundle is not None
        and hotspot_results is not None
    )

    if not all_loaded:
        return error_response(
            "One or more models could not be reloaded.",
            503,
            {
                "monthly_model_error": monthly_model_error,
                "hotspot_model_error": hotspot_model_error,
                "hotspot_results_error": hotspot_results_error,
            },
        )

    return success_response(
        message="All machine learning resources were reloaded successfully."
    )


# ============================================================
# ERROR HANDLERS
# ============================================================

@app.errorhandler(404)
def not_found(_error):
    return error_response(
        "API endpoint not found.",
        404,
    )


@app.errorhandler(405)
def method_not_allowed(_error):
    return error_response(
        "HTTP method not allowed for this endpoint.",
        405,
    )


@app.errorhandler(500)
def internal_server_error(error):
    logger.exception(
        "Unhandled API error: %s",
        error,
    )

    return error_response(
        "An internal server error occurred.",
        500,
    )


# ============================================================
# START SERVER
# ============================================================

if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=5001,
        debug=False,
        threaded=True,
        use_reloader=False,
    )
