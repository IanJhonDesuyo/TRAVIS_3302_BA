from pathlib import Path
import json

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
)
from sklearn.model_selection import (
    train_test_split,
    StratifiedKFold,
    cross_val_score,
)


BASE_DIR = Path(__file__).resolve().parent
DATA_PATH = BASE_DIR / "dataset" / "monthly_prediction.csv"
MODEL_DIR = BASE_DIR / "models"
RESULTS_DIR = BASE_DIR / "results"

MODEL_DIR.mkdir(exist_ok=True)
RESULTS_DIR.mkdir(exist_ok=True)


def assign_risk_level(
    count: float,
    low_threshold: float,
    high_threshold: float,
) -> str:
    if count <= low_threshold:
        return "Low"

    if count <= high_threshold:
        return "Medium"

    return "High"


def main() -> None:
    df = pd.read_csv(DATA_PATH)

    # Remove extra spaces from column names.
    df.columns = df.columns.str.strip()

    required_columns = {
        "Year",
        "Month",
        "Count of Violation",
    }

    missing_columns = required_columns.difference(df.columns)

    if missing_columns:
        raise ValueError(
            f"Missing required columns: {sorted(missing_columns)}"
        )

    df = df.dropna(
        subset=["Year", "Month", "Count of Violation"]
    ).copy()

    df["Year"] = pd.to_numeric(df["Year"], errors="coerce")
    df["Month"] = pd.to_numeric(df["Month"], errors="coerce")
    df["Count of Violation"] = pd.to_numeric(
        df["Count of Violation"],
        errors="coerce",
    )

    df = df.dropna().copy()

    df["Year"] = df["Year"].astype(int)
    df["Month"] = df["Month"].astype(int)

    # Validate month values.
    df = df[df["Month"].between(1, 12)].copy()

    if len(df) < 12:
        raise ValueError(
            "The monthly dataset has too few valid records."
        )

    # Divide historical counts into three data-based groups.
    low_threshold = float(
        df["Count of Violation"].quantile(1 / 3)
    )
    high_threshold = float(
        df["Count of Violation"].quantile(2 / 3)
    )

    df["Risk Level"] = df["Count of Violation"].apply(
        lambda value: assign_risk_level(
            value,
            low_threshold,
            high_threshold,
        )
    )

    print("\nDataset size:", len(df))
    print("\nRisk distribution:")
    print(df["Risk Level"].value_counts())
    print("\nRisk thresholds:")
    print(f"Low: count <= {low_threshold:.2f}")
    print(
        f"Medium: {low_threshold:.2f} < count "
        f"<= {high_threshold:.2f}"
    )
    print(f"High: count > {high_threshold:.2f}")

    X = df[["Year", "Month"]]
    y = df["Risk Level"]

    # A small test set is used because the dataset is limited.
    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y,
        test_size=0.25,
        random_state=42,
        stratify=y,
    )

    model = RandomForestClassifier()

    cv = StratifiedKFold(
        n_splits=5,
        shuffle=True,
        random_state=42
    )

    scores = cross_val_score(
        model,
        X,
        y,
        cv=cv,
        scoring="accuracy"
    )

    print("\n========== CROSS VALIDATION ==========")
    print(scores)
    print(f"\nAverage Accuracy: {scores.mean()*100:.2f}%")
    print(f"Std Deviation : {scores.std()*100:.2f}%")

    model.fit(X_train, y_train)

    predictions = model.predict(X_test)

    accuracy = accuracy_score(y_test, predictions)
    report = classification_report(
        y_test,
        predictions,
        zero_division=0,
    )
    matrix = confusion_matrix(
        y_test,
        predictions,
        labels=["Low", "Medium", "High"],
    )

    print(f"\nTest accuracy: {accuracy:.4f}")
    print("\nClassification report:")
    print(report)
    print("\nConfusion matrix:")
    print(matrix)

    # Train the final model using all available records.
    model.fit(X, y)

    model_bundle = {
        "model": model,
        "features": ["Year", "Month"],
        "classes": list(model.classes_),
        "low_threshold": low_threshold,
        "high_threshold": high_threshold,
    }

    joblib.dump(
        model_bundle,
        MODEL_DIR / "monthly_risk_model.pkl",
    )

    df.to_csv(
        RESULTS_DIR / "monthly_data_with_risk.csv",
        index=False,
    )

    metrics = {
        "dataset_rows": int(len(df)),
        "test_accuracy": float(accuracy),
        "low_threshold": low_threshold,
        "high_threshold": high_threshold,
        "risk_distribution": {
            str(key): int(value)
            for key, value
            in df["Risk Level"].value_counts().items()
        },
    }

    with open(
        RESULTS_DIR / "monthly_metrics.json",
        "w",
        encoding="utf-8",
    ) as file:
        json.dump(metrics, file, indent=2)

    print("\nSaved files:")
    print(MODEL_DIR / "monthly_risk_model.pkl")
    print(RESULTS_DIR / "monthly_data_with_risk.csv")
    print(RESULTS_DIR / "monthly_metrics.json")


if __name__ == "__main__":
    main()