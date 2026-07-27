from __future__ import annotations

from pathlib import Path
import json
import sys
from typing import Any

import joblib
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler


# ============================================================
# TRAVIS HOTSPOT CLUSTERING MODEL
# Algorithm: K-Means Clustering
#
# Purpose:
# Group locations according to similar historical traffic
# violation patterns and label the groups as Low, Medium,
# or High Risk for TMO decision support.
# ============================================================


BASE_DIR = Path(__file__).resolve().parent

DATA_PATH = (
    BASE_DIR
    / "dataset"
    / "hotspot_cluster.csv"
)

MODEL_DIR = BASE_DIR / "models"
RESULTS_DIR = BASE_DIR / "results"

MODEL_DIR.mkdir(parents=True, exist_ok=True)
RESULTS_DIR.mkdir(parents=True, exist_ok=True)

MODEL_PATH = MODEL_DIR / "hotspot_clustering_model.pkl"
CLUSTERS_CSV_PATH = RESULTS_DIR / "hotspot_clusters.csv"
METRICS_JSON_PATH = RESULTS_DIR / "hotspot_metrics.json"
PROFILES_CSV_PATH = RESULTS_DIR / "hotspot_cluster_profiles.csv"
ELBOW_PLOT_PATH = RESULTS_DIR / "hotspot_elbow_method.png"
SILHOUETTE_PLOT_PATH = RESULTS_DIR / "hotspot_silhouette_scores.png"
PCA_PLOT_PATH = RESULTS_DIR / "hotspot_cluster_visualization.png"


# ============================================================
# CONFIGURATION
# ============================================================

RANDOM_STATE = 42
MAX_CLUSTERS_TO_TEST = 6
KMEANS_N_INIT = 20

# Set to None to automatically choose the best cluster count
# Set to 3 if you want High / Medium / Low Risk
NUMBER_OF_CLUSTERS = 3

# The location name column is expected to use one of these names.
LOCATION_COLUMN_CANDIDATES = [
    "Location",
    "LOCATION",
    "location",
]

# This derived total column is used for assigning risk levels,
# but it is excluded from K-Means inputs to avoid double-counting
# the same violation information.
TOTAL_COLUMN_CANDIDATES = [
    "Total Violations",
    "Total_Violations",
    "TOTAL VIOLATIONS",
    "Total",
    "TOTAL",
]


# ============================================================
# HELPERS
# ============================================================

def normalize_column_name(value: object) -> str:
    """Return a clean, single-spaced column name."""
    return " ".join(str(value).strip().split())


def find_first_existing_column(
    columns: list[str],
    candidates: list[str],
) -> str | None:
    """Return the first candidate that exists in columns."""
    for candidate in candidates:
        if candidate in columns:
            return candidate
    return None


def recommendation_for_risk(risk_level: str) -> list[str]:
    """Return decision-support recommendations for each risk level."""
    recommendations = {
        "High Risk": [
            "Prioritize the location for traffic-enforcer deployment.",
            "Increase patrol visibility during peak traffic periods.",
            "Closely monitor congestion and possible road incidents.",
            "Prepare public advisories when traffic conditions worsen.",
        ],
        "Medium Risk": [
            "Maintain regular patrol visibility.",
            "Schedule additional monitoring during busy periods.",
            "Review recurring violation types in the area.",
        ],
        "Low Risk": [
            "Continue routine traffic monitoring.",
            "Maintain the standard enforcer schedule.",
            "Reassess the location when new records become available.",
        ],
    }
    return recommendations.get(risk_level, [])


# ============================================================
# DATA LOADING AND VALIDATION
# ============================================================

def load_dataset() -> tuple[pd.DataFrame, str, str]:
    """
    Load and validate the clustering dataset.

    Expected structure:
        Location | Violation A | Violation B | ... | Total Violations

    Each row must represent one unique location.
    """

    if not DATA_PATH.exists():
        raise FileNotFoundError(
            "Dataset not found:\n"
            f"{DATA_PATH}\n\n"
            "Place location_clustering_dataset.xlsx inside:\n"
            f"{BASE_DIR / 'dataset'}"
        )

    if DATA_PATH.suffix.lower() in {".xlsx", ".xls"}:
        df = pd.read_excel(DATA_PATH)
    elif DATA_PATH.suffix.lower() == ".csv":
        df = pd.read_csv(DATA_PATH)
    else:
        raise ValueError(
            "Unsupported dataset format. Use .xlsx, .xls, or .csv."
        )

    df.columns = [
        normalize_column_name(column)
        for column in df.columns
    ]

    location_column = find_first_existing_column(
        list(df.columns),
        LOCATION_COLUMN_CANDIDATES,
    )

    if location_column is None:
        raise ValueError(
            "No Location column was found. "
            f"Available columns: {list(df.columns)}"
        )

    total_column = find_first_existing_column(
        list(df.columns),
        TOTAL_COLUMN_CANDIDATES,
    )

    # Remove completely blank rows.
    df = df.dropna(how="all").copy()

    # Clean locations.
    df[location_column] = (
        df[location_column]
        .astype(str)
        .str.strip()
        .str.replace(r"\s+", " ", regex=True)
    )

    # Remove invalid location entries.
    df = df[
        (df[location_column] != "")
        & (df[location_column].str.lower() != "nan")
    ].copy()

    if df.empty:
        raise ValueError("The dataset has no valid location records.")

    # Convert all non-location columns to numeric where possible.
    for column in df.columns:
        if column == location_column:
            continue

        df[column] = pd.to_numeric(
            df[column],
            errors="coerce",
        ).fillna(0)

    # If a total column does not exist, create it.
    if total_column is None:
        numeric_columns = [
            column
            for column in df.columns
            if column != location_column
        ]

        if not numeric_columns:
            raise ValueError(
                "No numeric violation columns were found."
            )

        total_column = "Total Violations"
        df[total_column] = df[numeric_columns].sum(axis=1)

    # Aggregate duplicate locations if any.
    numeric_columns = [
        column
        for column in df.columns
        if column != location_column
    ]

    df = (
        df.groupby(location_column, as_index=False)[numeric_columns]
        .sum()
    )

    if len(df) < 3:
        raise ValueError(
            "At least three unique locations are needed for clustering."
        )

    if (df[total_column] <= 0).all():
        raise ValueError(
            "All locations have zero historical violations."
        )

    return df, location_column, total_column


# ============================================================
# FEATURE PREPARATION
# ============================================================

def prepare_features(
    df: pd.DataFrame,
    location_column: str,
    total_column: str,
) -> tuple[pd.DataFrame, list[str]]:
    """
    Build the K-Means input matrix.

    Total Violations is excluded because it is already the sum
    of the individual violation columns and would duplicate
    information. It is still used later to label cluster risk.
    """

    feature_columns = [
        column
        for column in df.columns
        if column not in {location_column, total_column}
    ]

    # Keep only columns with at least one non-zero value.
    feature_columns = [
        column
        for column in feature_columns
        if float(df[column].sum()) > 0
    ]

    if not feature_columns:
        raise ValueError(
            "No usable violation-count feature columns were found."
        )

    X = df[feature_columns].astype(float).copy()

    # Remove constant columns because they do not help clustering.
    non_constant_columns = [
        column
        for column in X.columns
        if X[column].nunique() > 1
    ]

    if not non_constant_columns:
        raise ValueError(
            "All violation columns are constant. "
            "Clustering cannot be performed."
        )

    X = X[non_constant_columns]

    return X, list(X.columns)


# ============================================================
# CLUSTER SELECTION
# ============================================================

def evaluate_cluster_counts(
    X_scaled: np.ndarray,
) -> tuple[pd.DataFrame, int]:
    """
    Evaluate candidate cluster counts using:
    - Inertia for the Elbow Method
    - Silhouette Score for cluster separation

    The best k is selected using the highest silhouette score.
    """

    sample_count = len(X_scaled)

    maximum_k = min(
        MAX_CLUSTERS_TO_TEST,
        sample_count - 1,
    )

    if maximum_k < 2:
        raise ValueError(
            "There are not enough locations to test clustering."
        )

    records: list[dict[str, float | int]] = []

    for cluster_count in range(2, maximum_k + 1):
        model = KMeans(
            n_clusters=cluster_count,
            n_init=KMEANS_N_INIT,
            random_state=RANDOM_STATE,
        )

        labels = model.fit_predict(X_scaled)

        # Silhouette requires at least 2 unique clusters and fewer
        # clusters than total samples.
        unique_labels = np.unique(labels)

        if (
            len(unique_labels) < 2
            or len(unique_labels) >= sample_count
        ):
            score = float("nan")
        else:
            score = float(
                silhouette_score(X_scaled, labels)
            )

        records.append(
            {
                "clusters": cluster_count,
                "inertia": float(model.inertia_),
                "silhouette_score": score,
            }
        )

    evaluation = pd.DataFrame(records)

    valid_scores = evaluation.dropna(
        subset=["silhouette_score"]
    )

    if valid_scores.empty:
        # Safe fallback when a silhouette score cannot be computed.
        best_k = min(3, maximum_k)
    else:
        best_row = valid_scores.loc[
            valid_scores["silhouette_score"].idxmax()
        ]
        best_k = int(best_row["clusters"])

    return evaluation, best_k


# ============================================================
# RISK LABELING
# ============================================================

def build_risk_mapping(
    results_df: pd.DataFrame,
    total_column: str,
) -> tuple[dict[int, str], pd.DataFrame]:
    """
    Assign human-readable risk levels to arbitrary K-Means IDs.

    Cluster IDs are sorted by their mean Total Violations:
    - Lowest mean -> Low Risk
    - Highest mean -> High Risk
    - Middle clusters -> Medium Risk
    """

    profiles = (
        results_df.groupby("Cluster")
        .agg(
            Location_Count=("Location", "count"),
            Mean_Total_Violations=(total_column, "mean"),
            Median_Total_Violations=(total_column, "median"),
            Min_Total_Violations=(total_column, "min"),
            Max_Total_Violations=(total_column, "max"),
            Sum_Total_Violations=(total_column, "sum"),
        )
        .reset_index()
        .sort_values("Mean_Total_Violations")
        .reset_index(drop=True)
    )

    cluster_ids = profiles["Cluster"].astype(int).tolist()
    cluster_count = len(cluster_ids)

    risk_mapping: dict[int, str] = {}

    if cluster_count == 1:
        risk_mapping[cluster_ids[0]] = "Medium Risk"

    elif cluster_count == 2:
        risk_mapping[cluster_ids[0]] = "Low Risk"
        risk_mapping[cluster_ids[1]] = "High Risk"

    else:
        for index, cluster_id in enumerate(cluster_ids):
            if index == 0:
                risk_mapping[cluster_id] = "Low Risk"
            elif index == cluster_count - 1:
                risk_mapping[cluster_id] = "High Risk"
            else:
                risk_mapping[cluster_id] = "Medium Risk"

    profiles["Risk Level"] = profiles["Cluster"].map(
        risk_mapping
    )

    return risk_mapping, profiles


# ============================================================
# VISUALIZATIONS
# ============================================================

def save_elbow_plot(evaluation: pd.DataFrame) -> None:
    """Save the Elbow Method graph."""
    plt.figure(figsize=(8, 5))
    plt.plot(
        evaluation["clusters"],
        evaluation["inertia"],
        marker="o",
    )
    plt.title("TRAVIS Hotspot Clustering - Elbow Method")
    plt.xlabel("Number of Clusters (k)")
    plt.ylabel("Inertia")
    plt.xticks(evaluation["clusters"])
    plt.grid(alpha=0.25)
    plt.tight_layout()
    plt.savefig(ELBOW_PLOT_PATH, dpi=180)
    plt.close()


def save_silhouette_plot(evaluation: pd.DataFrame) -> None:
    """Save silhouette scores for each tested k."""
    plt.figure(figsize=(8, 5))
    plt.plot(
        evaluation["clusters"],
        evaluation["silhouette_score"],
        marker="o",
    )
    plt.title("TRAVIS Hotspot Clustering - Silhouette Scores")
    plt.xlabel("Number of Clusters (k)")
    plt.ylabel("Silhouette Score")
    plt.xticks(evaluation["clusters"])
    plt.grid(alpha=0.25)
    plt.tight_layout()
    plt.savefig(SILHOUETTE_PLOT_PATH, dpi=180)
    plt.close()


def save_pca_visualization(
    X_scaled: np.ndarray,
    cluster_labels: np.ndarray,
    locations: pd.Series,
) -> None:
    """
    Reduce the features to two PCA dimensions and save a
    cluster visualization.
    """

    pca = PCA(n_components=2)
    reduced = pca.fit_transform(X_scaled)

    plt.figure(figsize=(11, 7))
    scatter = plt.scatter(
        reduced[:, 0],
        reduced[:, 1],
        c=cluster_labels,
        s=90,
        alpha=0.85,
    )

    for index, location in enumerate(locations):
        plt.annotate(
            str(location),
            (reduced[index, 0], reduced[index, 1]),
            xytext=(5, 5),
            textcoords="offset points",
            fontsize=8,
        )

    plt.title("TRAVIS Location Hotspot Clusters (PCA View)")
    plt.xlabel("Principal Component 1")
    plt.ylabel("Principal Component 2")
    plt.grid(alpha=0.2)
    plt.tight_layout()
    plt.savefig(PCA_PLOT_PATH, dpi=180)
    plt.close()


# ============================================================
# TRAINING
# ============================================================

def train_hotspot_model() -> None:
    """Run the complete hotspot clustering workflow."""

    print("\n==========================================")
    print("TRAVIS HOTSPOT CLUSTERING MODEL")
    print("Algorithm: K-Means Clustering")
    print("==========================================")

    df, location_column, total_column = load_dataset()

    print(f"\nUnique locations: {len(df)}")
    print(
        "Total historical violations:",
        int(df[total_column].sum()),
    )

    X, feature_columns = prepare_features(
        df,
        location_column,
        total_column,
    )

    print("Violation features used:", len(feature_columns))

    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    evaluation, best_k = evaluate_cluster_counts(X_scaled)

    print("\nCluster evaluation:")
    print(evaluation.to_string(index=False))

    if NUMBER_OF_CLUSTERS is not None:
        best_k = NUMBER_OF_CLUSTERS 
        print(f"\nUsing user-defined number of clusters: {best_k}")
    else:
        print(f"\nAutomatically selected number of clusters: {best_k}")

    model = KMeans(
        n_clusters=best_k,
        n_init=KMEANS_N_INIT,
        random_state=RANDOM_STATE,
    )

    cluster_labels = model.fit_predict(X_scaled)

    final_silhouette = float(
        silhouette_score(X_scaled, cluster_labels)
    )

    results_df = df.copy()
    results_df = results_df.rename(
        columns={location_column: "Location"}
    )
    results_df["Cluster"] = cluster_labels.astype(int)

    risk_mapping, cluster_profiles = build_risk_mapping(
        results_df,
        total_column,
    )

    results_df["Risk Level"] = results_df["Cluster"].map(
        risk_mapping
    )

    results_df["Recommendation"] = results_df[
        "Risk Level"
    ].apply(
        lambda risk: " ".join(
            recommendation_for_risk(str(risk))
        )
    )

    # Sort by risk and violation count for dashboard use.
    risk_order = {
        "High Risk": 0,
        "Medium Risk": 1,
        "Low Risk": 2,
    }

    results_df["_risk_order"] = results_df[
        "Risk Level"
    ].map(risk_order)

    results_df = (
        results_df.sort_values(
            by=["_risk_order", total_column],
            ascending=[True, False],
        )
        .drop(columns=["_risk_order"])
        .reset_index(drop=True)
    )

    # Save charts.
    save_elbow_plot(evaluation)
    save_silhouette_plot(evaluation)
    save_pca_visualization(
        X_scaled,
        cluster_labels,
        results_df.sort_values("Cluster")["Location"]
        if False
        else df[location_column],
    )

    # Save tables.
    results_df.to_csv(
        CLUSTERS_CSV_PATH,
        index=False,
    )

    cluster_profiles.to_csv(
        PROFILES_CSV_PATH,
        index=False,
    )

    # Save model bundle for future integration.
    model_bundle = {
        "model": model,
        "scaler": scaler,
        "feature_columns": feature_columns,
        "location_column": "Location",
        "total_column": total_column,
        "risk_mapping": risk_mapping,
        "cluster_profiles": cluster_profiles.to_dict(
            orient="records"
        ),
        "algorithm": "K-Means Clustering",
        "model_version": "1.0",
        "random_state": RANDOM_STATE,
    }

    joblib.dump(
        model_bundle,
        MODEL_PATH,
    )

    best_evaluation_row = evaluation.loc[
        evaluation["clusters"] == best_k
    ].iloc[0]

    metrics: dict[str, Any] = {
        "algorithm": "K-Means Clustering",
        "dataset_file": DATA_PATH.name,
        "unique_locations": int(len(df)),
        "total_historical_violations": int(
            df[total_column].sum()
        ),
        "feature_count": int(len(feature_columns)),
        "features": feature_columns,
        "tested_cluster_counts": evaluation.to_dict(
            orient="records"
        ),
        "selected_clusters": int(best_k),
        "selected_inertia": float(
            best_evaluation_row["inertia"]
        ),
        "silhouette_score": final_silhouette,
        "risk_mapping": {
            str(cluster_id): risk_level
            for cluster_id, risk_level in risk_mapping.items()
        },
        "cluster_profiles": cluster_profiles.to_dict(
            orient="records"
        ),
        "interpretation": (
            "Locations are grouped according to similar historical "
            "traffic violation patterns. Risk labels are assigned "
            "by ordering clusters according to their mean total "
            "historical violations."
        ),
        "limitation": (
            "The clusters reflect historical patterns from the "
            "available TMO records and do not guarantee future "
            "traffic conditions. The model should be retrained "
            "when additional records become available."
        ),
    }

    with open(
        METRICS_JSON_PATH,
        "w",
        encoding="utf-8",
    ) as file:
        json.dump(
            metrics,
            file,
            indent=2,
            ensure_ascii=False,
        )

    print("\n========== CLUSTER PROFILES ==========")
    print(cluster_profiles.to_string(index=False))

    print("\n========== LOCATION RESULTS ==========")
    print(
        results_df[
            [
                "Location",
                total_column,
                "Cluster",
                "Risk Level",
            ]
        ].to_string(index=False)
    )

    print(f"\nSilhouette Score: {final_silhouette:.4f}")

    print("\nSaved files:")
    print(MODEL_PATH)
    print(CLUSTERS_CSV_PATH)
    print(PROFILES_CSV_PATH)
    print(METRICS_JSON_PATH)
    print(ELBOW_PLOT_PATH)
    print(SILHOUETTE_PLOT_PATH)
    print(PCA_PLOT_PATH)

    print("\nTraining completed successfully.")


if __name__ == "__main__":
    try:
        train_hotspot_model()
    except Exception as error:
        print("\nTraining failed:")
        print(error)
        sys.exit(1)
