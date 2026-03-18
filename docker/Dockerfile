FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Tokyo

RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    build-essential \
    pkg-config \
    libssl-dev \
    libudev-dev \
    ca-certificates \
    bzip2 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace

RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y --default-toolchain 1.75.0
ENV PATH="/root/.cargo/bin:$PATH"

RUN rustc --version && \
    cargo --version && \
    rustup component add rustfmt clippy

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g yarn && \
    rm -rf /var/lib/apt/lists/*

RUN wget https://github.com/solana-labs/solana/releases/download/v1.17.31/solana-release-x86_64-unknown-linux-gnu.tar.bz2 && \
    tar -xjf solana-release-x86_64-unknown-linux-gnu.tar.bz2 && \
    mkdir -p /root/.local/share && \
    mv solana-release /root/.local/share/solana && \
    rm solana-release-x86_64-unknown-linux-gnu.tar.bz2

ENV PATH="/root/.local/share/solana/bin:$PATH"

RUN solana --version && \
    solana config set --url devnet

RUN cargo install --git https://github.com/coral-xyz/anchor --tag v0.29.0 anchor-cli --locked --force

RUN anchor --version

RUN echo "=== Environment Information ===" && \
    echo "Rust version:" && rustc --version && \
    echo "Cargo version:" && cargo --version && \
    echo "Solana version:" && solana --version && \
    echo "Anchor version:" && anchor --version && \
    echo "Node version:" && node --version && \
    echo "Yarn version:" && yarn --version

WORKDIR /workspace/project

CMD ["/bin/bash"]